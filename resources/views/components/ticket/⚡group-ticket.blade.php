<?php

use App\Livewire\Concerns\NotifiesWithToast;
use App\Services\CosmiaApi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    use NotifiesWithToast;

    /** Endpoint du service Cosmia (POST, body JSON : {"ticket_id": int}) */
    public const ENDPOINT = '/ticket/getTicketSet';

    /** Endpoint (PUT, body JSON : {"ticket_id": "15035"}) : clôture tout le groupe */
    public const CLOSE_ALL_ENDPOINT = '/ticket/closeAllTicketSet';

    /** L'endpoint n8n met ~11 s à répondre : on laisse de la marge */
    public const TIMEOUT = 60;

    /** Durée du cache du groupe, en minutes */
    public const CACHE_MINUTES = 5;

    /** Durée de mémorisation d'un statut connu, en secondes (24 h) */
    public const STATUS_CACHE_SECONDS = 86400;

    /** Mêmes statuts que la page détail (ticket/show) */
    public const STATUSES = [
        'en attente' => ['title' => 'En attente', 'color' => 'amber'],
        'en cours'   => ['title' => 'En cours',   'color' => 'blue'],
        'cloture'    => ['title' => 'Clôturé',    'color' => 'green'],
    ];

    /** Valeurs de num_commande à considérer comme « pas de commande » */
    public const EMPTY_ORDERS = ['inconnu', 'n/a', 'na', '-', '[numero de la commande introuvable]'];

    /** label_id => [texte, couleur du badge Flux] */
    public const LABELS = [
        1  => ['text' => 'Suivi de commande',             'color' => 'sky'],
        2  => ['text' => 'Colis non reçu',                'color' => 'red'],
        3  => ['text' => 'Paiement',                      'color' => 'amber'],
        4  => ['text' => 'Facture non reçue',             'color' => 'amber'],
        5  => ['text' => 'Produit défectueux',            'color' => 'red'],
        6  => ['text' => 'Retour produit & rétractation', 'color' => 'purple'],
        7  => ['text' => 'Demande spécifique',            'color' => 'indigo'],
        8  => ['text' => 'Colis vide',                    'color' => 'red'],
        9  => ['text' => 'Spam / publicité',              'color' => 'zinc'],
        10 => ['text' => 'Changement adresse',            'color' => 'teal'],
        11 => ['text' => 'Inversion de colis',            'color' => 'orange'],
    ];

    #[Locked]
    public int $ticketId = 0;

    #[Locked]
    public int $activeId = 0;

    /** Tous les tickets du groupe (y compris le ticket actif) */
    #[Locked]
    public array $tickets = [];

    /** false tant que l'API n'a pas répondu */
    public bool $loaded = false;

    public ?string $error = null;

    /** Détail technique (message cURL), affiché uniquement en mode debug */
    public ?string $errorDetail = null;

    public string $search = '';

    public function mount(int $ticketId, int $activeId = 0): void
    {
        $this->ticketId = $ticketId;
        // Par défaut, le ticket actif est celui dont on demande le groupe
        $this->activeId = $activeId ?: $ticketId;
    }

    private function cacheKey(): string
    {
        return "ticket-set:{$this->ticketId}";
    }

    private function statusCacheKey(int $id): string
    {
        return "ticket-status:{$id}";
    }

    /** true si le statut (brut) correspond à « clôturé » */
    public static function isClosed(mixed $status): bool
    {
        return in_array(mb_strtolower(trim((string) $status)), ['cloture', 'clôturé', 'cloturé'], true);
    }

    /** Chargement différé : le bouton s'affiche tout de suite, le compteur arrive après */
    public function loadTickets(CosmiaApi $api): void
    {
        if ($this->loaded || $this->ticketId === 0) {
            return;
        }

        // Dev uniquement : l'API n8n est lente, on évite le timeout PHP de 30 s
        set_time_limit(90);

        try {
            // remember() ne stocke rien si la closure lève une exception : les erreurs ne sont pas mises en cache
            $res = Cache::remember(
                $this->cacheKey(),
                now()->addMinutes(self::CACHE_MINUTES),
                fn () => $api->post(self::ENDPOINT, [
                    'ticket_id' => $this->ticketId,
                ], timeout: self::TIMEOUT)
            );

            // getTicketSet ne renvoie pas le statut : on applique ceux déjà connus (cache, aucun appel API)
            $this->tickets     = $this->applyKnownStatuses($this->extractTickets($res));
            $this->error       = null;
            $this->errorDetail = null;
        } catch (\RuntimeException $e) {
            Log::warning('getTicketSet KO', [
                'ticket' => $this->ticketId,
                'error'  => $e->getMessage(),
                'cause'  => $e->getPrevious()?->getMessage(),
            ]);

            $this->tickets     = [];
            $this->error       = $e->getMessage();
            $this->errorDetail = $e->getPrevious()?->getMessage();
        }

        $this->loaded = true;
    }

    /** Bouton « Réessayer » (ignore le cache du groupe, garde les statuts connus) */
    public function retry(CosmiaApi $api): void
    {
        Cache::forget($this->cacheKey());

        $this->loaded      = false;
        $this->error       = null;
        $this->errorDetail = null;
        $this->loadTickets($api);
    }

    /** Applique les statuts déjà connus (cache uniquement, aucun appel API) */
    private function applyKnownStatuses(array $tickets): array
    {
        foreach ($tickets as $i => $t) {
            $known = Cache::get($this->statusCacheKey((int) $t['id']));

            if ($known !== null) {
                $tickets[$i]['status'] = $known;
            }
        }

        return $tickets;
    }

    /**
     * Même logique que updateStatus() de la page détail :
     * PUT /ticket/{id} avec ['status' => $newStatus], sur un ticket du groupe.
     */
    public function updateStatus(int $id, string $newStatus): void
    {
        if (! isset(self::STATUSES[$newStatus])) {
            return;
        }

        // Sécurité : l'id vient du navigateur, on ne touche que les tickets de ce groupe
        $index = collect($this->tickets)->search(fn ($t) => (int) ($t['id'] ?? 0) === $id);

        if ($index === false) {
            return;
        }

        $api = app(CosmiaApi::class);

        try {
            $res = $api->put("/ticket/{$id}", ['status' => $newStatus]);
        } catch (\RuntimeException $e) {
            Log::warning('group updateStatus KO', ['ticket' => $id, 'status' => $newStatus, 'error' => $e->getMessage()]);
            $this->notify(__('Impossible de mettre à jour le ticket !'), 'danger');

            return;
        }

        Log::info('group updateStatus OK', ['ticket' => $id, 'status' => $newStatus, 'response' => $res]);

        // On mémorise le statut : il survit au refresh sans rappeler l'API
        Cache::put($this->statusCacheKey($id), $newStatus, now()->addSeconds(self::STATUS_CACHE_SECONDS));

        $this->tickets[$index]['status'] = $newStatus;

        $this->notify(__('Le ticket est maintenant en statut : :status', [
            'status' => self::STATUSES[$newStatus]['title'],
        ]));
    }

    /** Raccourci : clôture un ticket du groupe */
    public function closeTicket(int $id): void
    {
        $this->updateStatus($id, 'cloture');
    }

    /**
     * Clôture tous les tickets du groupe : PUT /ticket/closeAllTicketSet
     * body : {"ticket_id": "15035"}
     */
    public function closeAll(): void
    {
        if ($this->ticketId === 0 || $this->tickets === []) {
            return;
        }

        // Dev uniquement : l'API n8n peut être lente
        set_time_limit(90);

        try {
            $res = app(CosmiaApi::class)->post(self::CLOSE_ALL_ENDPOINT, [
                'ticket_id' => (string) $this->ticketId,
            ]);
        } catch (\RuntimeException $e) {
            Log::warning('closeAllTicketSet KO', [
                'ticket' => $this->ticketId,
                'error'  => $e->getMessage(),
                'cause'  => $e->getPrevious()?->getMessage(),
            ]);

            $this->notify(__('Impossible de clôturer les tickets !'), 'danger');

            return;
        }

        Log::info('closeAllTicketSet OK', ['ticket' => $this->ticketId, 'response' => $res]);

        // Mise à jour locale + mémorisation du statut pour chaque ticket du groupe
        foreach ($this->tickets as $i => $t) {
            $this->tickets[$i]['status'] = 'cloture';

            Cache::put(
                $this->statusCacheKey((int) $t['id']),
                'cloture',
                now()->addSeconds(self::STATUS_CACHE_SECONDS)
            );
        }

        // Le ticket de la page fait partie du groupe : on demande à la page de se rafraîchir
        $this->dispatch('ticket-updated');

        $this->notify(__('Tous les tickets associés ont été clôturés'));
    }

    /** Normalise la réponse de l'API en liste de tickets. */
    private function extractTickets(array $res): array
    {
        $list = $res['tickets']
            ?? $res['data']['tickets']
            ?? $res['data']
            ?? $res;

        if (! is_array($list)) {
            return [];
        }

        return array_values(array_filter(
            $list,
            fn ($t) => is_array($t) && isset($t['id'])
        ));
    }

    /** Numéro de commande exploitable, ou null */
    public static function cleanOrder(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || in_array(mb_strtolower($value), self::EMPTY_ORDERS, true)) {
            return null;
        }

        return $value;
    }

    /** Tickets du groupe, sans le ticket actuellement ouvert */
    #[Computed]
    public function others(): array
    {
        return array_values(array_filter(
            $this->tickets,
            fn ($t) => (int) ($t['id'] ?? 0) !== $this->activeId
        ));
    }

    /** Nombre de tickets du groupe pas encore clôturés (statut inconnu = ouvert) */
    #[Computed]
    public function openCount(): int
    {
        return count(array_filter(
            $this->tickets,
            fn ($t) => ! self::isClosed($t['status'] ?? null)
        ));
    }

    /** Résultat de la recherche (n° ticket, n° commande, id) */
    #[Computed]
    public function filtered(): array
    {
        $q = mb_strtolower(trim($this->search));

        if ($q === '') {
            return $this->others;
        }

        return array_values(array_filter(
            $this->others,
            fn ($t) => str_contains(
                mb_strtolower(
                    ($t['num_ticket'] ?? '').' '.(self::cleanOrder($t['num_commande'] ?? null) ?? '').' '.($t['id'] ?? '')
                ),
                $q
            )
        ));
    }
};
?>

@php
    // ── Style de carte (même rendu que la page ticket et le tiroir de réponse) ──
    $card      = 'overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50 dark:border-white/10 dark:bg-zinc-900';
    $cardHead  = 'px-4 py-3';
    $cardTitle = 'text-sm font-semibold text-zinc-900 dark:text-white';
    $cardDesc  = 'mt-0.5 text-xs text-zinc-500 dark:text-zinc-400';
    $panel     = 'mx-1 mb-1 overflow-hidden rounded-xl border border-zinc-300/70 bg-white dark:border-white/10 dark:bg-zinc-800';
    $divide    = 'divide-y divide-zinc-200/70 dark:divide-white/10';
@endphp

<div wire:init="loadTickets">

    {{-- Bouton toujours visible --}}
    <flux:modal.trigger :name="'group-tickets-'.$ticketId">
        <flux:button size="sm" icon="rectangle-stack">
            Tickets associés

            @if (! $loaded)
                <flux:icon.loading variant="micro" />
            @elseif ($error)
                <flux:badge size="sm" color="red" inset="top bottom">!</flux:badge>
            @else
                <flux:badge size="sm" :color="count($this->others) > 0 ? 'blue' : 'zinc'" inset="top bottom">
                    {{ count($this->others) }}
                </flux:badge>
            @endif
        </flux:button>
    </flux:modal.trigger>

    {{-- Drawer --}}
    <flux:modal :name="'group-tickets-'.$ticketId" variant="flyout" class="w-full max-w-md">
        <div class="flex h-full flex-col gap-4">

            <div>
                <flux:heading size="lg" class="text-blue-600 dark:text-blue-400">Tickets associés</flux:heading>
                <flux:text class="mt-1 text-blue-500/80 dark:text-blue-300/80">Autres tickets liés à la même demande.</flux:text>
            </div>

            @if (! $loaded)
                {{-- Squelettes au style des cartes --}}
                <div class="space-y-3">
                    @foreach (range(1, 4) as $i)
                        <div class="{{ $card }} animate-pulse">
                            <div class="{{ $cardHead }} space-y-2">
                                <div class="h-4 w-32 rounded bg-zinc-200 dark:bg-white/10"></div>
                                <div class="h-3 w-20 rounded bg-zinc-200 dark:bg-white/10"></div>
                            </div>
                            <div class="{{ $panel }} px-4 py-3">
                                <div class="h-5 w-40 rounded bg-zinc-200 dark:bg-white/10"></div>
                            </div>
                        </div>
                    @endforeach
                </div>

            @elseif ($error)
                <flux:callout variant="danger" icon="exclamation-circle" :heading="$error">
                    @if ($errorDetail && app()->hasDebugModeEnabled())
                        <flux:callout.text class="break-all font-mono text-xs">
                            {{ $errorDetail }}
                        </flux:callout.text>
                    @endif

                    <x-slot name="actions">
                        <flux:button size="sm" wire:click="retry">Réessayer</flux:button>
                    </x-slot>
                </flux:callout>

            @elseif (empty($this->others))
                {{-- État vide : carte grise + panneau blanc, comme « No invoices yet » --}}
                <div class="{{ $card }}">
                    <div class="{{ $cardHead }}">
                        <div class="{{ $cardTitle }}">Tickets associés</div>
                        <div class="{{ $cardDesc }}">Autres tickets liés à la même demande.</div>
                    </div>

                    <div class="{{ $panel }}">
                        <div class="flex flex-col items-center gap-1 px-4 py-8 text-center">
                            <flux:icon.inbox class="size-7 text-zinc-300 dark:text-zinc-600" />
                            <div class="text-sm font-semibold text-zinc-900 dark:text-white">Aucun autre ticket associé</div>
                            <div class="text-xs text-zinc-400 dark:text-zinc-500">Ce ticket n'est lié à aucun autre ticket.</div>
                        </div>
                    </div>
                </div>

            @else
                @if (count($this->others) > 5)
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        icon="magnifying-glass"
                        placeholder="Rechercher (n° ticket, commande…)"
                        clearable
                    />
                @endif

                {{-- Compteur + action « tout clôturer » --}}
                <div class="-mb-1 flex items-center justify-between gap-2">
                    <flux:text size="sm">
                        {{ count($this->filtered) }} ticket{{ count($this->filtered) > 1 ? 's' : '' }}
                    </flux:text>

                    @if ($this->openCount > 0)
                        <flux:button
                            size="sm"
                            variant="ghost"
                            class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                            wire:click="closeAll"
                            wire:confirm="Clôturer tous les tickets associés ({{ $this->openCount }} ticket{{ $this->openCount > 1 ? 's' : '' }} ouvert{{ $this->openCount > 1 ? 's' : '' }}) ?"
                            wire:loading.attr="disabled"
                            wire:target="closeAll"
                        >
                            <span wire:loading.remove wire:target="closeAll">Clôturer tous les tickets</span>
                            <span wire:loading wire:target="closeAll">Clôture en cours…</span>
                        </flux:button>
                    @endif
                </div>

                <ul class="-mx-2 flex-1 space-y-3 overflow-y-auto px-2">
                    @forelse ($this->filtered as $ticket)
                        @php
                            $label    = self::LABELS[$ticket['label_id'] ?? 0] ?? ['text' => 'Inconnu', 'color' => 'zinc'];
                            $commande = self::cleanOrder($ticket['num_commande'] ?? null);
                            $status   = mb_strtolower(trim((string) ($ticket['status'] ?? '')));
                            $closed   = self::isClosed($status);
                            $numLabel = $ticket['num_ticket'] ?? '#'.$ticket['id'];
                        @endphp

                        <li wire:key="group-ticket-{{ $ticket['id'] }}">
                            <div class="{{ $card }}">

                                {{-- En-tête cliquable : n° du ticket + id, lien vers le ticket --}}
                                <a
                                    href="{{ route('kanban.details', ['ticket' => $ticket['id']]) }}"
                                    wire:navigate
                                    class="group flex items-center justify-between gap-3 {{ $cardHead }} transition hover:bg-zinc-100/70 dark:hover:bg-white/5"
                                >
                                    <div class="min-w-0">
                                        <div class="{{ $cardTitle }} truncate">{{ $ticket['num_ticket'] ?? '—' }}</div>
                                        <div class="{{ $cardDesc }} font-mono">#{{ $ticket['id'] }}</div>
                                    </div>

                                    <flux:icon.chevron-right
                                        variant="micro"
                                        class="shrink-0 text-zinc-400 transition group-hover:translate-x-0.5"
                                    />
                                </a>

                                {{-- Panneau blanc : badges, commande, action --}}
                                <div class="{{ $panel }} {{ $divide }}">
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 px-4 py-3">
                                        <flux:badge size="sm" :color="$label['color']">{{ $label['text'] }}</flux:badge>

                                        @if ($closed)
                                            <flux:badge size="sm" color="green" icon="check-circle">Clôturé</flux:badge>
                                        @elseif (isset(self::STATUSES[$status]))
                                            <flux:badge size="sm" :color="self::STATUSES[$status]['color']">
                                                {{ self::STATUSES[$status]['title'] }}
                                            </flux:badge>
                                        @endif

                                        @if ($commande)
                                            <flux:text size="sm" class="flex min-w-0 items-center gap-1">
                                                <flux:icon.shopping-bag variant="micro" class="shrink-0 text-zinc-400" />
                                                <span class="truncate" title="{{ $commande }}">{{ $commande }}</span>
                                            </flux:text>
                                        @endif
                                    </div>

                                    {{-- Pied : uniquement si le ticket n'est pas clôturé --}}
                                    @unless ($closed)
                                        <div class="flex items-center justify-between gap-2 px-4 py-2.5">
                                            <flux:text size="sm">Ce ticket est encore ouvert</flux:text>

                                            <flux:button
                                                size="sm"
                                                variant="danger"
                                                wire:click="updateStatus({{ (int) $ticket['id'] }}, 'cloture')"
                                                wire:confirm="Clôturer le ticket {{ $numLabel }} ?"
                                                wire:loading.attr="disabled"
                                                wire:target="updateStatus({{ (int) $ticket['id'] }}, 'cloture')"
                                            >
                                                Clôturer le ticket
                                            </flux:button>
                                        </div>
                                    @endunless
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="py-8 text-center">
                            <flux:text size="sm">Aucun résultat pour « {{ $search }} ».</flux:text>
                        </li>
                    @endforelse
                </ul>
            @endif
        </div>
    </flux:modal>
</div>
