<?php

use App\Services\CosmiaApi;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    private const PER_PAGE = 10;

    // Les classes 'border' sont écrites en entier pour que Tailwind les détecte.
    public const STATUSES = [
        'en attente' => [
            'title'  => 'En attente',
            'dot'    => 'bg-amber-500',
            'border' => 'from-amber-400 to-amber-400/10',
        ],
        'en cours'   => [
            'title'  => 'En cours',
            'dot'    => 'bg-blue-500',
            'border' => 'from-blue-400 to-blue-400/10',
        ],
        'cloture'    => [
            'title'  => 'Clôturé',
            'dot'    => 'bg-green-500',
            'border' => 'from-green-400 to-green-400/10',
        ],
    ];

    // ⚠️ id => libellé. 1, 2, 3, 5 et 7 sont confirmés par les tickets ; les autres sont déduits de l'ordre des catégories.
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

    public string $projectId = 'all';

    public string $labelId = '';

    public string $search = '';

    /** @var array<string, array{tickets: array, page: int, lastPage: int, total: int, error: ?string}> */
    public array $columns = [];

    public function mount(): void
    {
        try {
            $this->projects = app(CosmiaApi::class)->get('/project');
        } catch (\RuntimeException) {
            $this->projects = [];
        }

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

        return [
            'id'        => $t['id'] ?? null,
            'num'       => $t['num_ticket'] ?? '',
            'subject'   => trim((string) ($t['subject_ticket'] ?? '')),
            'client'    => $client,
            'order'     => in_array(strtolower($order), ['', 'inconnu', 'unknown'], true) ? null : $order,
            'label'     => trim((string) ($t['label'] ?? '')),
            'project'   => $t['project_name'] ?? null,
            'attention' => (bool) ($t['need_attention'] ?? false),
            // Format : 25 Janvier 2026
            'date'      => ! empty($t['created_at'])
                ? Str::title(Carbon::parse($t['created_at'])->locale('fr')->translatedFormat('j F Y'))
                : null,
        ];
    }
};
