<?php

use App\Services\CosmiaApi;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

new class extends Component
{
    // Les statistiques bougent peu à l'échelle de quelques minutes : cache court (secondes)
    private const CACHE_TTL = 120;

    // Valeur envoyée à l'API (date_range) => libellé affiché
    public const RANGES = [
        0 => '7 Jours',
        1 => '15 Jours',
        2 => '1 Mois',
        3 => '3 Mois',
        4 => '6 Mois',
        5 => '1 Ans',
    ];

    // ⚠️ À adapter : valeur envoyée à l'API (ticket_status) => libellé affiché
    public const STATUSES = [
        'all'         => 'Tous les statuts',
        'pending'     => 'En attente',
        'in_progress' => 'En cours',
        'closed'      => 'Clôturé',
    ];

    public int $range = 0;

    public string $status = 'all';

    public int $total = 0;

    public array $labels = [];

    public array $values = [];

    public ?string $error = null;

    /** false tant que les données n'ont pas été chargées (rendu initial = squelette) */
    public bool $loaded = false;

    /**
     * Cache chaud : graphique complet dès la première réponse, sans squelette ni 2e requête.
     * Cache vide : squelette, puis load() via wire:init.
     */
    public function mount(): void
    {
        $cached = Cache::get($this->cacheKey());

        if (is_array($cached)) {
            $this->apply($cached);
            $this->loaded = true;
        }
    }

    public function updatedRange(): void
    {
        $this->refreshChart();
    }

    public function updatedStatus(): void
    {
        $this->refreshChart();
    }

    /** Chargement différé (wire:init) */
    public function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->refreshChart();
    }

    /** $force = true : « Réessayer » / « Actualiser » (ignore le cache) */
    public function refreshChart(bool $force = false): void
    {
        $this->loadData($force);
        $this->loaded = true;

        $this->dispatch('tickets-chart-updated', labels: $this->labels, values: $this->values);
    }

    // Clé liée à la session : les droits de l'API dépendent du token de l'utilisateur
    private function cacheKey(): string
    {
        return 'cosmia.dash.tickets.'.sha1((string) session('cosmia_token')).'.'.$this->range.'.'.$this->status;
    }

    private function apply(array $data): void
    {
        $this->total  = $data['total'];
        $this->labels = $data['labels'];
        $this->values = $data['values'];
    }

    private function loadData(bool $force = false): void
    {
        $this->error = null;

        // Valeurs modifiables depuis le navigateur : on n'envoie à l'API que des valeurs connues
        if (! isset(self::RANGES[$this->range])) {
            $this->range = 0;
        }

        if (! isset(self::STATUSES[$this->status])) {
            $this->status = 'all';
        }

        $key = $this->cacheKey();

        if (! $force && is_array($cached = Cache::get($key))) {
            $this->apply($cached);

            return;
        }

        try {
            $response = app(CosmiaApi::class)->post('/dash/getticketsummary', [
                'ticket_status' => $this->status,
                'date_range'    => $this->range,
            ]);
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage());

            return;
        }

        $details = collect($response['details'] ?? [])->sortBy('period')->values();

        $data = [
            'total'  => (int) ($response['total'] ?? 0),
            'labels' => $details->map(fn ($d) => $this->formatPeriod((string) ($d['period'] ?? '')))->all(),
            'values' => $details->map(fn ($d) => (int) ($d['nombre'] ?? 0))->all(),
        ];

        // Les erreurs ne sont jamais mises en cache
        Cache::put($key, $data, self::CACHE_TTL);

        $this->apply($data);
    }

    // "2026-08-19" => "19/08" ; "2025-10" => "10/2025" ; autre format => tel quel
    private function formatPeriod(string $period): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $period)) {
            return Carbon::createFromFormat('Y-m-d', $period)->format('d/m');
        }

        if (preg_match('/^\d{4}-\d{2}$/', $period)) {
            return Carbon::createFromFormat('!Y-m', $period)->format('m/Y');
        }

        return $period;
    }

    private function fail(string $message): void
    {
        $this->error  = $message;
        $this->total  = 0;
        $this->labels = [];
        $this->values = [];
    }
};
?>

@assets
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
@endassets

{{-- wire:init sur un <div> : Blade n'accepte pas @if dans les attributs d'un composant <flux:...> --}}
<div @if (! $loaded) wire:init="load" @endif>
    <flux:card class="flex flex-col gap-4 bg-zinc-100 dark:bg-zinc-700/60 border-zinc-200 dark:border-zinc-600">
        <div class="flex items-start justify-between gap-3">
            <div>
                <flux:heading size="lg">{{ __('Tickets') }}</flux:heading>
                <flux:text class="text-sm">
                    {{ __('Total sur la période :') }}
                    @if ($loaded)
                        <span class="font-semibold">{{ number_format($total, 0, ',', ' ') }}</span>
                    @else
                        <span class="inline-block h-4 w-10 animate-pulse rounded bg-zinc-200 align-middle dark:bg-zinc-600"></span>
                    @endif
                </flux:text>
            </div>

            {{-- Les données sont gardées 2 min côté serveur : ce bouton force une mise à jour --}}
            @if ($loaded)
                <flux:button
                    size="sm"
                    variant="ghost"
                    wire:click="refreshChart(true)"
                    wire:loading.attr="disabled"
                    wire:target="refreshChart"
                    :aria-label="__('Actualiser')"
                >
                    <i class="hgi-stroke hgi-refresh" wire:loading.remove wire:target="refreshChart"></i>
                    <i class="hgi-stroke hgi-loading-03 animate-spin" wire:loading wire:target="refreshChart"></i>
                </flux:button>
            @endif
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <flux:select wire:model.live="range" :label="__('Période (jours)')">
                @foreach ($this::RANGES as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="status" :label="__('Filtrer par statut')">
                @foreach ($this::STATUSES as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($error)
            <flux:callout variant="danger" icon="exclamation-circle" :heading="$error">
                <x-slot name="actions">
                    <flux:button size="sm" wire:click="refreshChart(true)">{{ __('Réessayer') }}</flux:button>
                </x-slot>
            </flux:callout>
        @endif

        {{-- Conteneur relatif : le squelette (Livewire) recouvre le graphique (Alpine) --}}
        <div
            class="relative h-72 transition-opacity"
            wire:loading.class="opacity-50"
            wire:target="range,status,refreshChart"
        >
            @if (! $loaded)
                <div class="absolute inset-0 z-10 flex animate-pulse items-end gap-2 rounded-lg bg-zinc-100 p-4 dark:bg-zinc-700/60">
                    @foreach ([40, 65, 50, 80, 60, 90, 70] as $h)
                        <div wire:key="sk-bar-{{ $loop->index }}" class="flex-1 rounded-t bg-zinc-200 dark:bg-zinc-600" style="height: {{ $h }}%"></div>
                    @endforeach
                </div>
            @endif

            <div
                wire:ignore
                class="h-full"
                x-data="(() => {
                    let chart = null;
                    let pending = null;

                    const colors = () => {
                        const dark = document.documentElement.classList.contains('dark');
                        return {
                            text: dark ? '#a1a1aa' : '#52525b',
                            grid: dark ? 'rgba(161,161,170,0.15)' : 'rgba(82,82,91,0.15)',
                        };
                    };

                    return {
                        init() {
                            const c = colors();

                            chart = new Chart(this.$refs.canvas, {
                                type: 'line',
                                data: {
                                    labels: @js($labels),
                                    datasets: [{
                                        label: 'Tickets',
                                        data: @js($values),
                                        borderColor: '#3b82f6',
                                        backgroundColor: 'rgba(59,130,246,0.15)',
                                        fill: true,
                                        tension: 0.35,
                                        pointRadius: 3,
                                        pointHoverRadius: 5,
                                    }],
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    interaction: { mode: 'index', intersect: false },
                                    plugins: { legend: { display: false } },
                                    scales: {
                                        x: { ticks: { color: c.text }, grid: { color: c.grid } },
                                        y: { beginAtZero: true, ticks: { color: c.text, precision: 0 }, grid: { color: c.grid } },
                                    },
                                },
                            });

                            // Données reçues avant la création du graphique
                            if (pending) {
                                this.update(pending);
                                pending = null;
                            }
                        },

                        update(detail) {
                            if (! chart) {
                                pending = detail;
                                return;
                            }

                            chart.data.labels = detail.labels;
                            chart.data.datasets[0].data = detail.values;
                            chart.update();
                        },

                        destroy() {
                            chart?.destroy();
                            chart = null;
                        },
                    };
                })()"
                x-on:tickets-chart-updated.window="update($event.detail)"
            >
                <canvas x-ref="canvas"></canvas>
            </div>
        </div>
    </flux:card>
</div>
