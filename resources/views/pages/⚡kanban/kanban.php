<?php

use App\Services\CosmiaApi;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    private const PER_PAGE = 10;

    private const CACHE_PROJECTS_TTL = 600;  // secondes
    private const CACHE_USERS_TTL    = 600;
    private const CACHE_USERPROJECT_TTL = 300;

    public const STATUSES = [
        'en attente' => ['title' => 'En attente', 'dot' => 'bg-amber-500'],
        'en cours'   => ['title' => 'En cours',   'dot' => 'bg-blue-500'],
        'cloture'    => ['title' => 'Clôturé',    'dot' => 'bg-green-500'],
    ];

    public const LABELS = [
        1  => 'Suivi de commande',
        2  => 'Colis non reçu',
        3  => 'Paiement',
        4  => 'Facture non reçue',
        5  => 'Produit défectueux',
        6  => 'Retour/Rétractation',
        7  => 'Demande spécifique',
        8  => 'Colis vide',
        9  => 'Spam',
        10 => 'Changement adresse',
        11 => 'Inversion colis',
    ];

    protected $avatarColors = [
        'red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald',
        'teal', 'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple',
        'fuchsia', 'pink', 'rose',
    ];

    /** false tant que les données API n'ont pas été chargées (rendu initial = squelettes) */
    public bool $ready = false;

    /** Projets visibles par l'utilisateur connecté */
    public array $projects = [];

    /**
     * null = accès à tous les projets (super_admin) ;
     * sinon liste d'ids de projets autorisés (string).
     * Verrouillé : non modifiable depuis le navigateur.
     */
    #[Locked]
    public ?array $allowedProjectIds = null;

    /**
     * true si super_admin ou admin : seuls ces rôles peuvent assigner
     * un ticket à un autre utilisateur. Verrouillé : non modifiable depuis le navigateur.
     */
    #[Locked]
    public bool $canAssign = false;

    /** Projet affiché ('all' réservé au super_admin). Verrouillé : change via setProject(). */
    #[Locked]
    public string $projectId = 'all';

    /** @var array<int, string> [id => nom] des utilisateurs assignables (treating = 1) */
    public array $users = [];

    /** @var array<int, string> [id => nom] de tous les utilisateurs (pour afficher l'assigné d'une carte) */
    public array $userNames = [];

    public string $labelId = '';

    public string $search = '';

    /** Filtre « Ticket qui m'est assigné » */
    public bool $mine = false;

    /** Id Cosmia de l'utilisateur connecté (affichage uniquement, les actions utilisent currentUserId()) */
    public ?int $meId = null;

    /** @var array<string, array{tickets: array, page: int, lastPage: int, total: int, error: ?string}> */
    public array $columns = [];

    /**
     * Rendu initial : uniquement des lectures de session (aucun appel API).
     * Le chargement réel se fait dans loadData(), déclenché par wire:init.
     */
    public function mount(): void
    {
        $this->meId      = $this->currentUserId();
        $this->canAssign = $this->canAssignTickets();

        // Fail closed : non-admin = aucun projet tant que l'API n'a pas répondu
        $this->allowedProjectIds = $this->isSuperAdmin() ? null : [];

        if ($this->allowedProjectIds !== null) {
            $this->projectId = '';
        }
    }

    /**
     * Chargement différé (wire:init) :
     *  - tour 1 : /project, /user et /user/userproject/{id} en parallèle (sauf ce qui est en cache)
     *  - tour 2 : les 3 colonnes de tickets en parallèle (loadAll)
     */
    public function loadData(): void
    {
        if ($this->ready) {
            return;
        }

        $api        = app(CosmiaApi::class);
        $me         = $this->currentUserId();
        $superAdmin = $this->isSuperAdmin();

        // Cache d'abord
        $projects     = Cache::get('cosmia.projects');
        $users        = Cache::get('cosmia.users');
        $userProjects = ($superAdmin || $me === null) ? null : Cache::get("cosmia.userproject.{$me}");

        // Ce qui manque est demandé en une seule salve parallèle
        $requests = [];

        if ($projects === null) {
            $requests['projects'] = ['/project'];
        }

        if ($users === null) {
            $requests['users'] = ['/user'];
        }

        if (! $superAdmin && $me !== null && $userProjects === null) {
            $requests['userproject'] = ['/user/userproject/'.$me];
        }

        if ($requests !== []) {
            $results = $api->pool($requests);

            // Les erreurs ne sont jamais mises en cache

            if (isset($results['projects'])) {
                if ($results['projects'] instanceof \Throwable) {
                    $projects = [];
                } else {
                    $projects = $results['projects'];
                    Cache::put('cosmia.projects', $projects, self::CACHE_PROJECTS_TTL);
                }
            }

            if (isset($results['users'])) {
                if ($results['users'] instanceof \Throwable) {
                    $users = []; // menus vides, le reste du board fonctionne
                } else {
                    $users = $this->normalizeUsers($results['users']);
                    Cache::put('cosmia.users', $users, self::CACHE_USERS_TTL);
                }
            }

            if (isset($results['userproject']) && ! ($results['userproject'] instanceof \Throwable)) {
                $userProjects = collect($results['userproject']['result'] ?? [])
                    ->pluck('project_id')
                    ->map(fn ($id) => (string) $id)
                    ->unique()
                    ->values()
                    ->all();

                Cache::put("cosmia.userproject.{$me}", $userProjects, self::CACHE_USERPROJECT_TTL);
            }
        }

        $projects ??= [];
        $users    ??= [];

        // null = super_admin ; sinon ids autorisés (erreur ou pas d'id => [] : fail closed)
        $this->allowedProjectIds = $superAdmin ? null : ($userProjects ?? []);

        // Non-admin : on ne garde que les projets auxquels l'utilisateur est affecté
        $this->projects = $this->allowedProjectIds === null
            ? $projects
            : collect($projects)
                ->filter(fn ($p) => in_array((string) $p['id'], $this->allowedProjectIds, true))
                ->values()
                ->all();

        // Non-admin : pas de vue « Tous », on démarre sur son premier projet
        if ($this->allowedProjectIds !== null) {
            $this->projectId = isset($this->projects[0]) ? (string) $this->projects[0]['id'] : '';
        }

        $this->applyUsers($users);

        $this->ready = true;
        $this->loadAll();
    }

    public function colorForName(string $name): string
    {
        return $this->avatarColors[crc32($name) % count($this->avatarColors)];
    }

    public function setProject(string $id): void
    {
        if (! $this->ready) {
            return;
        }

        // Non-admin : uniquement ses propres projets, et pas de « all »
        if ($this->allowedProjectIds !== null && ! in_array($id, $this->allowedProjectIds, true)) {
            return;
        }

        $this->projectId = $id;
        $this->loadAll();
    }

    public function updatedLabelId(): void
    {
        $this->loadAll();
    }

    public function updatedSearch(): void
    {
        $this->loadAll();
    }

    public function toggleMine(): void
    {
        if (! $this->ready) {
            return;
        }

        $this->mine = ! $this->mine;
        $this->loadAll();
    }

    public function loadMore(string $status): void
    {
        if (! isset(self::STATUSES[$status])) {
            return;
        }

        $column = $this->columns[$status] ?? null;

        // Rien à charger : erreur, dernière page atteinte ou tout est déjà affiché
        if (
            $column === null
            || $column['error']
            || $column['page'] >= $column['lastPage']
            || count($column['tickets']) >= $column['total']
        ) {
            return;
        }

        $this->fetchColumn($status, $column['page'] + 1);
    }

    /**
     * Drag & drop : déplace un ticket vers une autre colonne et met à jour son statut via l'API.
     * Ne travaille que sur les tickets déjà chargés (donc filtrés par projet).
     */
    public function moveTicket(int|string $id, string $to): void
    {
        if (! isset(self::STATUSES[$to])) {
            return;
        }

        // Retrouver la colonne d'origine et le ticket
        $from = null;
        $card = null;

        foreach ($this->columns as $status => $column) {
            foreach ($column['tickets'] as $ticket) {
                if ((string) $ticket['id'] === (string) $id) {
                    $from = $status;
                    $card = $ticket;
                    break 2;
                }
            }
        }

        if ($from === null || $from === $to) {
            return;
        }

        try {
            app(CosmiaApi::class)->put("/ticket/{$id}", ['status' => $to]);
        } catch (\RuntimeException $e) {
            // Échec : on ne touche pas au board, on affiche l'erreur dans la colonne cible
            $this->columns[$to]['error'] = $e->getMessage();

            return;
        }

        // Succès : mise à jour locale sans recharger toutes les colonnes
        $this->columns[$from]['tickets'] = array_values(array_filter(
            $this->columns[$from]['tickets'],
            fn ($t) => (string) $t['id'] !== (string) $id
        ));
        $this->columns[$from]['total'] = max($this->columns[$from]['total'] - 1, 0);

        array_unshift($this->columns[$to]['tickets'], $card);
        $this->columns[$to]['total']++;
        $this->columns[$to]['error'] = null;

        // Notifie l'interface (message de confirmation côté Alpine)
        $this->dispatch(
            'ticket-moved',
            message: __('Ticket :num déplacé vers « :status »', [
                'num'    => $card['num'],
                'status' => self::STATUSES[$to]['title'],
            ])
        );
    }

    /**
     * Assigne un ticket à un utilisateur via l'API.
     * Réservé aux rôles super_admin et admin.
     */
    public function assignTicket(int|string $id, int $userId): void
    {
        // Contrôle serveur (vérifié à la source, pas seulement via la propriété verrouillée)
        if (! $this->canAssignTickets()) {
            $this->dispatch('ticket-moved', message: __("Vous n'êtes pas autorisé à assigner un ticket."));

            return;
        }

        if (! isset($this->users[$userId])) {
            $this->dispatch('ticket-moved', message: __('Utilisateur inconnu.'));

            return;
        }

        $this->applyAssignment($id, $userId, $this->users[$userId]);
    }

    /**
     * « Prendre le ticket » : l'assigne à l'utilisateur connecté.
     * Ouvert à tout utilisateur « treating ».
     */
    public function takeTicket(int|string $id): void
    {
        $me = $this->currentUserId();

        if ($me === null) {
            $this->dispatch('ticket-moved', message: __('Session expirée, reconnecte-toi.'));

            return;
        }

        // Seuls les utilisateurs « treating » peuvent prendre un ticket
        if (! isset($this->users[$me])) {
            $this->dispatch('ticket-moved', message: __("Vous n'êtes pas autorisé à prendre un ticket."));

            return;
        }

        $this->applyAssignment($id, $me, $this->users[$me], taken: true);
    }

    private function applyAssignment(int|string $id, int $userId, string $name, bool $taken = false): void
    {
        // Le ticket doit être affiché sur le board (donc dans un projet autorisé)
        $visible = collect($this->columns)
            ->flatMap(fn ($c) => $c['tickets'])
            ->contains(fn ($t) => (string) $t['id'] === (string) $id);

        if (! $visible) {
            $this->dispatch('ticket-moved', message: __('Ticket introuvable.'));

            return;
        }

        try {
            // Passer en post() si ton workflow n8n attend un POST
            app(CosmiaApi::class)->put("/ticket/{$id}", ['user_id' => $userId]);
        } catch (\RuntimeException $e) {
            $this->dispatch('ticket-moved', message: $e->getMessage());

            return;
        }

        // Filtre « mes tickets » actif et ticket confié à quelqu'un d'autre : il quitte le board
        $leavesBoard = $this->mine && $userId !== $this->currentUserId();
        $num = '';

        foreach ($this->columns as $status => $column) {
            foreach ($column['tickets'] as $i => $ticket) {
                if ((string) $ticket['id'] !== (string) $id) {
                    continue;
                }

                $num = $ticket['num'];

                if ($leavesBoard) {
                    unset($this->columns[$status]['tickets'][$i]);
                    $this->columns[$status]['tickets'] = array_values($this->columns[$status]['tickets']);
                    $this->columns[$status]['total']   = max($this->columns[$status]['total'] - 1, 0);
                } else {
                    $this->columns[$status]['tickets'][$i]['assignee_id'] = $userId;
                    $this->columns[$status]['tickets'][$i]['assignee']    = $name;
                }

                break 2;
            }
        }

        $this->dispatch(
            'ticket-moved',
            message: $taken
                ? __('Ticket :num pris en charge', ['num' => $num])
                : __('Ticket :num assigné à :name', ['num' => $num, 'name' => $name])
        );
    }

    /**
     * Payload du JWT Cosmia (session), ou tableau vide.
     */
    private function jwtPayload(): array
    {
        $token   = session('cosmia_token');
        $payload = is_string($token) ? (explode('.', $token)[1] ?? null) : null;

        if (! $payload) {
            return [];
        }

        return json_decode((string) base64_decode(strtr($payload, '-_', '+/')), true) ?: [];
    }

    /**
     * Rôle de l'utilisateur connecté (session, sinon payload du JWT).
     */
    private function currentRole(): ?string
    {
        $role = session('cosmia_role') ?? ($this->jwtPayload()['role'] ?? null);

        return is_string($role) ? $role : null;
    }

    /**
     * Id de l'utilisateur connecté, lu dans le payload du JWT Cosmia.
     */
    private function currentUserId(): ?int
    {
        $data = $this->jwtPayload();

        return isset($data['id']) ? (int) $data['id'] : null;
    }

    private function isSuperAdmin(): bool
    {
        return $this->currentRole() === 'super_admin';
    }

    /**
     * super_admin et admin peuvent assigner un ticket à n'importe quel utilisateur.
     * Adapter la liste si l'API utilise un autre libellé pour le rôle admin.
     */
    private function canAssignTickets(): bool
    {
        return in_array($this->currentRole(), ['super_admin', 'admin'], true);
    }

    /**
     * Réponse brute de /user => liste triée [id, name, treating] (format mis en cache).
     */
    private function normalizeUsers(array $raw): array
    {
        return collect($raw)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn ($u) => [
                'id'       => (int) $u['id'],
                'name'     => $u['name'],
                'treating' => (int) ($u['treating'] ?? 0),
            ])
            ->values()
            ->all();
    }

    private function applyUsers(array $all): void
    {
        $all = collect($all);

        // Tous les noms (affichage de l'assigné) / seulement treating = 1 (prendre, assigner)
        $this->userNames = $all->pluck('name', 'id')->all();
        $this->users     = $all->where('treating', 1)->pluck('name', 'id')->all();
    }

    /**
     * Non-admin : jamais de requête hors de ses projets.
     */
    private function isProjectAllowed(): bool
    {
        return $this->allowedProjectIds === null
            || in_array((string) $this->projectId, $this->allowedProjectIds, true);
    }

    private function emptyColumn(): array
    {
        return ['tickets' => [], 'page' => 1, 'lastPage' => 1, 'total' => 0, 'error' => null];
    }

    /**
     * Charge la 1re page des 3 colonnes en parallèle (un seul tour réseau).
     */
    private function loadAll(): void
    {
        if (! $this->ready) {
            return;
        }

        $statuses = array_keys(self::STATUSES);

        if (! $this->isProjectAllowed()) {
            foreach ($statuses as $status) {
                $this->columns[$status] = $this->emptyColumn();
            }

            return;
        }

        $requests = [];

        foreach ($statuses as $status) {
            $requests[$status] = ['/ticket', $this->queryFor($status, 1)];
        }

        $results = app(CosmiaApi::class)->pool($requests);

        foreach ($statuses as $status) {
            $this->hydrateColumn($status, 1, $results[$status]);
        }
    }

    /**
     * Page suivante d'une colonne (scroll infini).
     */
    private function fetchColumn(string $status, int $page): void
    {
        if (! $this->isProjectAllowed()) {
            $this->columns[$status] = $this->emptyColumn();

            return;
        }

        try {
            $result = app(CosmiaApi::class)->get('/ticket', $this->queryFor($status, $page));
        } catch (\RuntimeException $e) {
            $result = $e;
        }

        $this->hydrateColumn($status, $page, $result);
    }

    private function queryFor(string $status, int $page): array
    {
        // project_id : filtre confirmé (GET /ticket?project_id=X)
        // ⚠️ À confirmer côté API : page, per_page, status, label_id, search, user_id
        return array_filter([
            'page'       => $page,
            'per_page'   => self::PER_PAGE,
            'status'     => $status,
            'project_id' => $this->projectId === 'all' ? null : $this->projectId,
            'label_id'   => $this->labelId,
            'search'     => trim($this->search),
            'user_id'    => $this->mine ? $this->currentUserId() : null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Range dans $this->columns le résultat d'un appel /ticket (réponse décodée ou exception).
     */
    private function hydrateColumn(string $status, int $page, array|\Throwable $result): void
    {
        $previous = $this->columns[$status]['tickets'] ?? [];

        if ($result instanceof \Throwable) {
            $this->columns[$status] = [
                'tickets'  => $page === 1 ? [] : $previous,
                'page'     => $page === 1 ? 1 : $page - 1,
                'lastPage' => 1,
                'total'    => count($page === 1 ? [] : $previous),
                'error'    => $result->getMessage(),
            ];

            return;
        }

        $tickets = collect($result['data'] ?? [])->map(fn ($t) => $this->present($t))->all();

        $this->columns[$status] = [
            'tickets'  => $page === 1 ? $tickets : array_merge($previous, $tickets),
            'page'     => (int) ($result['current_page'] ?? $page),
            'lastPage' => (int) ($result['total_page'] ?? 1),
            'total'    => (int) ($result['total_item'] ?? count($tickets)),
            'error'    => null,
        ];
    }

    private function present(array $t): array
    {
        // "Eva Delem <eva@mail.com>" => "Eva Delem" ; "Anonyme" => e-mail du client
        $client = trim(preg_replace('/\s*<[^>]*>/', '', (string) ($t['nom_client'] ?? '')));

        if ($client === '' || strcasecmp($client, 'Anonyme') === 0) {
            $client = trim((string) ($t['original_client_mail'] ?? '')) ?: 'Anonyme';
        }

        $order = trim((string) ($t['num_commande'] ?? ''));

        // Date de création, convertie dans le fuseau de l'application
        $created = ! empty($t['created_at'])
            ? Carbon::parse($t['created_at'])->setTimezone(config('app.timezone'))->locale('fr')
            : null;

        // ⚠️ Nom du champ « utilisateur assigné » à confirmer dans la réponse de /ticket
        $assigneeId = isset($t['user_id']) && $t['user_id'] !== '' ? (int) $t['user_id'] : null;

        return [
            'id'          => $t['id'] ?? null,
            'num'         => $t['num_ticket'] ?? '',
            'subject'     => trim((string) ($t['subject_ticket'] ?? '')),
            'client'      => $client,
            'order'       => in_array(strtolower($order), ['', 'inconnu', 'unknown'], true) ? null : $order,
            'label'       => trim((string) ($t['label'] ?? '')),
            'project'     => $t['project_name'] ?? null,
            'attention'   => (bool) ($t['need_attention'] ?? false),
            'assignee_id' => $assigneeId,
            'assignee'    => $assigneeId ? ($this->userNames[$assigneeId] ?? null) : null,
            // Format : 25 Janvier 2026 / 14:32
            'date'        => $created ? Str::title($created->translatedFormat('j F Y')) : null,
            'time'        => $created?->format('H:i'),
        ];
    }
};
?>
