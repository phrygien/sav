<?php

use App\Services\CosmiaApi;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app')] class extends Component
{
    use WithPagination;

    // Cache court (secondes) par page, filtres et session
    private const CACHE_TTL = 60;

    // ⚠️ À compléter avec les valeurs réellement acceptées par l'API
    public const STATUSES = [
        'all' => 'Tous les statuts',
    ];

    public const RANGES = [
        1 => 'Période : 1',
    ];

    public const PER_PAGES = [10, 25, 50, 100];

    public string $ticketStatus = 'all';

    public int $dateRange = 1;

    public int $perPage = 10;

    public ?string $error = null;

    /** false tant que les données n'ont pas été chargées (rendu initial = squelette) */
    public bool $loaded = false;

    // Données récupérées pendant cette requête (évite de relire le cache)
    private ?array $fresh = null;

    /**
     * Cache chaud : tableau complet dès la première réponse, sans squelette ni 2e requête.
     * Cache vide : squelette, puis load() via wire:init.
     */
    public function mount(): void
    {
        $this->normalize();

        if (Cache::has($this->cacheKey())) {
            $this->loaded = true;
        }
    }

    /** Chargement différé (wire:init) */
    public function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;
    }

    /** « Actualiser » / « Réessayer » : ignore le cache */
    public function refresh(): void
    {
        $this->normalize();
        $this->fresh  = $this->fetch();
        $this->loaded = true;

        unset($this->payload, $this->tickets);
    }

    public function updatedTicketStatus(): void
    {
        $this->resetPage();
    }

    public function updatedDateRange(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    // Valeurs modifiables depuis le navigateur : on n'envoie à l'API que des valeurs connues
    private function normalize(): void
    {
        if (! isset(self::STATUSES[$this->ticketStatus])) {
            $this->ticketStatus = 'all';
        }

        if (! isset(self::RANGES[$this->dateRange])) {
            $this->dateRange = array_key_first(self::RANGES);
        }

        if (! in_array($this->perPage, self::PER_PAGES, true)) {
            $this->perPage = self::PER_PAGES[0];
        }
    }

    // Clé liée à la session : les droits de l'API dépendent du token de l'utilisateur
    private function cacheKey(): string
    {
        return 'cosmia.redundant.'.sha1((string) session('cosmia_token'))
            .'.'.$this->ticketStatus.'.'.$this->dateRange.'.'.$this->perPage.'.'.$this->getPage();
    }

    /**
     * Appel API + mise en forme (une seule fois, puis mis en cache).
     * En cas d'erreur : message dans $error, rien n'est mis en cache.
     */
    private function fetch(): array
    {
        $this->error = null;

        try {
            $response = app(CosmiaApi::class)->get('ticket/list/getRedudentTicket', [
                'ticket_status' => $this->ticketStatus,
                'date_range'    => $this->dateRange,
                'per_page'      => $this->perPage,
                'page'          => $this->getPage(),
            ]);
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();

            return ['rows' => [], 'total' => 0, 'perPage' => $this->perPage, 'page' => $this->getPage()];
        }

        $data = [
            'rows'    => collect($response['data'] ?? [])->map(fn ($g) => $this->presentGroup($g))->all(),
            'total'   => (int) ($response['total_item'] ?? 0),
            'perPage' => (int) ($response['per_page'] ?? $this->perPage),
            'page'    => (int) ($response['current_page'] ?? $this->getPage()),
        ];

        Cache::put($this->cacheKey(), $data, self::CACHE_TTL);

        return $data;
    }

    // Découpage / nettoyage des sujets fait ici, et non à chaque rendu
    private function presentGroup(array $group): array
    {
        $subjects = collect(explode(',', html_entity_decode((string) ($group['subjects_ticket'] ?? ''))))
            ->map(fn ($s) => trim(preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $s)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'mail'     => (string) ($group['original_client_mail'] ?? ''),
            'order'    => (string) ($group['num_commande'] ?? 'inconnu'),
            'count'    => (int) ($group['total_in_group'] ?? 0),
            'subjects' => $subjects,
        ];
    }

    /** Données de la page courante (serveur uniquement, jamais envoyées au navigateur) */
    #[Computed]
    public function payload(): array
    {
        $this->normalize();

        if ($this->fresh !== null) {
            return $this->fresh;
        }

        $cached = Cache::get($this->cacheKey());

        return is_array($cached) ? $cached : ($this->fresh = $this->fetch());
    }

    #[Computed]
    public function tickets(): LengthAwarePaginator
    {
        $data = $this->payload;

        return new LengthAwarePaginator(
            items: $data['rows'],
            total: $data['total'],
            perPage: $data['perPage'],
            currentPage: $data['page'],
            options: ['path' => request()->url()],
        );
    }
};
