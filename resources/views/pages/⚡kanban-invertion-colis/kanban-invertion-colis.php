<?php

use App\Services\CosmiaApi;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    private const PER_PAGE = 10;

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

    public array $projects = [];

    /** @var array<int, string> [id => nom] des utilisateurs assignables (treating = 1) */
    public array $users = [];

    /** @var array<int, string> [id => nom] de tous les utilisateurs (pour afficher l'assigné d'une carte) */
    public array $userNames = [];

    public string $projectId = 'all';

    public string $labelId = '11';

    public string $search = '';

    /** Filtre « Ticket qui m'est assigné » */
    public bool $mine = false;

    /** Id Cosmia de l'utilisateur connecté (affichage uniquement, l'action utilise currentUserId()) */
    public ?int $meId = null;

    /** @var array<string, array{tickets: array, page: int, lastPage: int, total: int, error: ?string}> */
    public array $columns = [];

    public function mount(): void
    {
        try {
            $this->projects = app(CosmiaApi::class)->get('/project');
        } catch (\RuntimeException) {
            $this->projects = [];
        }

        $this->meId = $this->currentUserId();
        $this->loadUsers();
        $this->loadAll();
    }

    public function colorForName(string $name): string
    {
        return $this->avatarColors[crc32($name) % count($this->avatarColors)];
    }

    public function setProject(string $id): void
    {
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
     */
    public function assignTicket(int|string $id, int $userId): void
    {
        if (! isset($this->users[$userId])) {
            $this->dispatch('ticket-moved', message: __('Utilisateur inconnu.'));

            return;
        }

        $this->applyAssignment($id, $userId, $this->users[$userId]);
    }

    /**
     * « Prendre le ticket » : l'assigne à l'utilisateur connecté.
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
     * Id de l'utilisateur connecté, lu dans le payload du JWT Cosmia (session).
     */
    private function currentUserId(): ?int
    {
        $token   = session('cosmia_token');
        $payload = is_string($token) ? (explode('.', $token)[1] ?? null) : null;

        if (! $payload) {
            return null;
        }

        $data = json_decode((string) base64_decode(strtr($payload, '-_', '+/')), true) ?: [];

        return isset($data['id']) ? (int) $data['id'] : null;
    }

    private function loadUsers(): void
    {
        $api = app(CosmiaApi::class);

        try {
            $all = Cache::remember('cosmia.users', now()->addMinutes(10), function () use ($api) {
                return collect($api->get('/user'))
                    ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->map(fn ($u) => [
                        'id'       => (int) $u['id'],
                        'name'     => $u['name'],
                        'treating' => (int) ($u['treating'] ?? 0),
                    ])
                    ->values()
                    ->all();
            });
        } catch (\RuntimeException) {
            $all = []; // menus vides, le reste du board fonctionne
        }

        $all = collect($all);

        // Tous les noms (affichage de l'assigné) / seulement treating = 1 (prendre, assigner)
        $this->userNames = $all->pluck('name', 'id')->all();
        $this->users     = $all->where('treating', 1)->pluck('name', 'id')->all();
    }

    private function loadAll(): void
    {
        foreach (array_keys(self::STATUSES) as $status) {
            $this->fetchColumn($status, 1);
        }
    }

    private function fetchColumn(string $status, int $page): void
    {
        $previous = $this->columns[$status]['tickets'] ?? [];

        // ⚠️ Noms des paramètres à confirmer côté API (page, per_page, status, project_id, label_id, search)
        $query = array_filter([
            'page'       => $page,
            'per_page'   => self::PER_PAGE,
            'status'     => $status,
            'project_id' => $this->projectId === 'all' ? null : $this->projectId,
            'label_id'   => $this->labelId,
            'search'     => trim($this->search),
            'user_id'    => $this->mine ? $this->currentUserId() : null,
        ], fn ($value) => $value !== null && $value !== '');

        try {
            $data = app(CosmiaApi::class)->get('/ticket', $query);

            $tickets = collect($data['data'] ?? [])->map(fn ($t) => $this->present($t))->all();

            $this->columns[$status] = [
                'tickets'  => $page === 1 ? $tickets : array_merge($previous, $tickets),
                'page'     => (int) ($data['current_page'] ?? $page),
                'lastPage' => (int) ($data['total_page'] ?? 1),
                'total'    => (int) ($data['total_item'] ?? count($tickets)),
                'error'    => null,
            ];
        } catch (\RuntimeException $e) {
            $this->columns[$status] = [
                'tickets'  => $page === 1 ? [] : $previous,
                'page'     => $page === 1 ? 1 : $page - 1,
                'lastPage' => 1,
                'total'    => count($page === 1 ? [] : $previous),
                'error'    => $e->getMessage(),
            ];
        }
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
