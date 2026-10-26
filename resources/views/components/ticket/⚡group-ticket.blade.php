<?php

use App\Services\CosmiaApi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    /** Endpoint du service Cosmia (POST, body JSON : {"ticket_id": int}) */
    public const ENDPOINT = '/ticket/getTicketSet';

    /** L'endpoint n8n met ~11 s à répondre : on laisse de la marge */
    public const TIMEOUT = 60;

    /** Durée du cache, en minutes */
    public const CACHE_MINUTES = 5;

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

    /** Chargement différé : le bouton s'affiche tout de suite, le compteur arrive après */
    public function loadTickets(CosmiaApi $api): void
    {
        if ($this->loaded || $this->ticketId === 0) {
            return;
        }

        try {
            // remember() ne stocke rien si la closure lève une exception : les erreurs ne sont pas mises en cache
            $res = Cache::remember(
                $this->cacheKey(),
                now()->addMinutes(self::CACHE_MINUTES),
                fn () => $api->post(self::ENDPOINT, [
                    'ticket_id' => $this->ticketId,
                ], timeout: self::TIMEOUT)
            );

            $this->tickets     = $this->extractTickets($res);
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

    /** Bouton « Réessayer » (ignore le cache) */
    public function retry(CosmiaApi $api): void
    {
        Cache::forget($this->cacheKey());

        $this->loaded      = false;
        $this->error       = null;
        $this->errorDetail = null;
        $this->loadTickets($api);
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
        <div class="flex h-full flex-col gap-5">

            <div>
                <flux:heading size="lg">Tickets associés</flux:heading>
                <flux:text class="mt-1">Autres tickets liés à la même demande.</flux:text>
            </div>

            @if (! $loaded)
                <div class="space-y-3">
                    @foreach (range(1, 4) as $i)
                        <div class="animate-pulse space-y-2 rounded-lg border border-zinc-200 p-3 dark:border-white/10">
                            <div class="h-4 w-32 rounded bg-zinc-200 dark:bg-white/10"></div>
                            <div class="h-3 w-24 rounded bg-zinc-200 dark:bg-white/10"></div>
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
                <div class="flex flex-1 flex-col items-center justify-center gap-2 py-10 text-center">
                    <flux:icon.inbox class="size-8 text-zinc-300 dark:text-zinc-600" />
                    <flux:heading>Aucun autre ticket associé</flux:heading>
                    <flux:text size="sm">Ce ticket n'est lié à aucun autre ticket.</flux:text>
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

                <flux:text size="sm" class="-mb-2">
                    {{ count($this->filtered) }} ticket{{ count($this->filtered) > 1 ? 's' : '' }}
                </flux:text>

                <ul class="-mx-2 flex-1 space-y-1 overflow-y-auto px-2">
                    @forelse ($this->filtered as $ticket)
                        @php
                            $label    = self::LABELS[$ticket['label_id'] ?? 0] ?? ['text' => 'Inconnu', 'color' => 'zinc'];
                            $commande = self::cleanOrder($ticket['num_commande'] ?? null);
                        @endphp

                        <li wire:key="group-ticket-{{ $ticket['id'] }}">
                            <a
                                href="{{ route('kanban.details', ['ticket' => $ticket['id']]) }}"
                                wire:navigate
                                class="group flex items-center gap-3 rounded-lg border border-zinc-200 p-3 transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-white/10 dark:hover:border-white/20 dark:hover:bg-white/5"
                            >
                                <div class="min-w-0 flex-1 space-y-1.5">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <flux:heading class="truncate">{{ $ticket['num_ticket'] ?? '—' }}</flux:heading>
                                        <span class="shrink-0 font-mono text-xs text-zinc-400">#{{ $ticket['id'] }}</span>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <flux:badge size="sm" :color="$label['color']">{{ $label['text'] }}</flux:badge>

                                        @if ($commande)
                                            <flux:text size="sm" class="flex min-w-0 items-center gap-1">
                                                <flux:icon.shopping-bag variant="micro" class="shrink-0 text-zinc-400" />
                                                <span class="truncate" title="{{ $commande }}">{{ $commande }}</span>
                                            </flux:text>
                                        @endif
                                    </div>
                                </div>

                                <flux:icon.chevron-right
                                    variant="micro"
                                    class="shrink-0 text-zinc-400 transition group-hover:translate-x-0.5"
                                />
                            </a>
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
