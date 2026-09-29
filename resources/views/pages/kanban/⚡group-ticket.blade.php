<?php
// resources/views/livewire/tikets/group-ticket.blade.php

use App\Services\CosmiaApi;
use Livewire\Component;

new class extends Component
{
    // Couleurs = variantes de <flux:badge>
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
        10 => ['text' => 'Changement adresse',            'color' => 'pink'],
        11 => ['text' => 'Inversion de colis',            'color' => 'amber'],
    ];

    public int $ticketId;

    public int $activeId = 0;

    public array $tickets = [];

    public bool $loading = true;

    public ?string $error = null;

    public function mount(int $ticketId, int $activeId = 0): void
    {
        $this->ticketId = $ticketId;
        $this->activeId = $activeId;
        $this->loadTickets();
    }

    public function loadTickets(): void
    {
        $this->loading = true;
        $this->error   = null;

        try {
            $data = app(CosmiaApi::class)->post('/ticket/getTicketSet', [
                'ticket_id' => (string) $this->ticketId,
            ]);
        } catch (\RuntimeException $e) {
            // Le message de CosmiaApi est déjà affichable tel quel
            $this->error   = $e->getMessage();
            $this->loading = false;

            return;
        }

        $fetched = $data['tickets'] ?? [];

        // Masque le groupe si le seul ticket retourné est le ticket actif lui-même
        if (count($fetched) === 1 && ($fetched[0]['id'] ?? null) === $this->activeId) {
            $fetched = [];
        }

        $this->tickets = $fetched;

        $this->loading = false;
    }
};
?>

<div class="w-full px-2 py-3">

    <style>
        /* Carte du ticket actuel : bordure dégradée */
        .ticket-card-active {
            --ticket-card-bg: #ffffff;
            background:
                linear-gradient(var(--ticket-card-bg), var(--ticket-card-bg)) padding-box,
                linear-gradient(135deg, #6366f1, #a855f7, #ec4899) border-box;
            border: 2px solid transparent !important;
        }
        .dark .ticket-card-active { --ticket-card-bg: #27272a; }

        #ticket-scroll { scrollbar-width: none; -ms-overflow-style: none; }
        #ticket-scroll::-webkit-scrollbar { display: none; }

        .scroll-arrow { transition: opacity .2s ease, transform .15s ease; }
        .scroll-arrow:hover { transform: translateY(-50%) scale(1.1); }
        .scroll-arrow.hidden-arrow { opacity: 0 !important; pointer-events: none; }
    </style>

    {{-- Chargement --}}
    @if ($loading)
        <div class="flex gap-4 overflow-x-auto pb-3">
            @foreach (range(1, 4) as $i)
                <flux:card size="sm" class="w-64 shrink-0">
                    <div class="flex items-center gap-2 text-zinc-400">
                        <flux:icon.loading class="size-4" />
                        <flux:text size="sm">{{ __('Chargement…') }}</flux:text>
                    </div>
                </flux:card>
            @endforeach
        </div>

        {{-- Erreur --}}
    @elseif ($error)
        <flux:callout variant="danger" icon="exclamation-triangle" :heading="$error" />

        {{-- Aucun résultat --}}
    @elseif (empty($tickets))
        <flux:card size="sm">
            <div class="flex items-center gap-2 text-zinc-500">
                <flux:icon.inbox class="size-4" />
                <flux:text size="sm">{{ __('Aucun ticket associé trouvé') }}</flux:text>
            </div>
        </flux:card>

        {{-- Résultats --}}
    @else
        <div class="relative">

            {{-- Flèche gauche --}}
            <flux:button
                id="scroll-btn-left"
                icon="chevron-left"
                size="sm"
                variant="filled"
                :aria-label="__('Défiler à gauche')"
                class="scroll-arrow hidden-arrow absolute top-1/2 z-10 -translate-y-1/2 !rounded-full shadow-sm"
                style="left: -8px; opacity: 0;"
            />

            {{-- Flèche droite --}}
            <flux:button
                id="scroll-btn-right"
                icon="chevron-right"
                size="sm"
                variant="filled"
                :aria-label="__('Défiler à droite')"
                class="scroll-arrow absolute top-1/2 z-10 -translate-y-1/2 !rounded-full shadow-sm"
                style="right: -8px;"
            />

            {{-- Liste des tickets --}}
            <div
                id="ticket-scroll"
                class="flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-smooth px-10 py-2"
            >
                @foreach ($tickets as $ticket)
                    @php
                        $label    = $this::LABELS[$ticket['label_id'] ?? null] ?? ['text' => 'Inconnu', 'color' => 'zinc'];
                        $commande = ($ticket['num_commande'] ?? 'inconnu') !== 'inconnu' ? $ticket['num_commande'] : '—';
                        $isActive = ($ticket['id'] ?? null) === $activeId;
                    @endphp

                    <flux:card
                        wire:key="group-ticket-{{ $ticket['id'] }}"
                        size="sm"
                        class="w-64 shrink-0 snap-start space-y-3 transition hover:shadow-md {{ $isActive ? 'ticket-card-active' : 'hover:bg-zinc-50 dark:hover:bg-white/[0.14]' }}"
                    >
                        {{-- Indicateur actif --}}
                        @if ($isActive)
                            <flux:badge color="indigo" size="sm">
                                <span class="flex items-center gap-1.5">
                                    <span class="inline-block size-1.5 animate-pulse rounded-full bg-indigo-500"></span>
                                    <span>{{ __('Ticket actuel') }}</span>
                                </span>
                            </flux:badge>
                        @endif

                        {{-- Titre --}}
                        <flux:heading>
                            <flux:link :href="route('kanban.details', $ticket['id'])" wire:navigate>
                                {{ $ticket['num_ticket'] }}
                            </flux:link>
                        </flux:heading>

                        {{-- Label --}}
                        <div>
                            <flux:badge :color="$label['color']" size="sm">{{ $label['text'] }}</flux:badge>
                        </div>

                        {{-- Infos --}}
                        <div class="flex items-center justify-between gap-2">
                            <flux:text size="sm">№ CMD / {{ $commande }}</flux:text>
                            <span class="font-mono text-xs text-zinc-400">#{{ $ticket['id'] }}</span>
                        </div>
                    </flux:card>
                @endforeach
            </div>
        </div>
    @endif
</div>

@script
<script>
    (function () {
        const root     = $wire.$el;
        const scroller = root.querySelector('#ticket-scroll');
        const btnLeft  = root.querySelector('#scroll-btn-left');
        const btnRight = root.querySelector('#scroll-btn-right');

        if (! scroller || ! btnLeft || ! btnRight) return;

        function updateArrows() {
            const atStart = scroller.scrollLeft <= 4;
            const atEnd   = scroller.scrollLeft + scroller.clientWidth >= scroller.scrollWidth - 4;

            btnLeft.classList.toggle('hidden-arrow', atStart);
            btnLeft.style.opacity = atStart ? '0' : '1';

            btnRight.classList.toggle('hidden-arrow', atEnd);
            btnRight.style.opacity = atEnd ? '0' : '1';
        }

        btnLeft.addEventListener('click',  () => scroller.scrollBy({ left: -272, behavior: 'smooth' }));
        btnRight.addEventListener('click', () => scroller.scrollBy({ left:  272, behavior: 'smooth' }));

        scroller.addEventListener('scroll', updateArrows, { passive: true });

        updateArrows();
    })();
</script>
@endscript
