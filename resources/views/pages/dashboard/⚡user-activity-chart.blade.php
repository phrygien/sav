<?php

use App\Services\CosmiaApi;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

new class extends Component
{
    // Cache court (secondes) : l'activité du jour évolue, mais pas à chaque seconde
    private const CACHE_TTL = 120;

    public const PALETTE = [
        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899',
        '#06b6d4', '#f97316', '#b8c925', '#14b8a6', '#eab308',
    ];

    public string $month = 'all';

    public string $year = 'all';

    public array $chart = ['labels' => [], 'series' => []];

    public bool $empty = true;

    public ?string $error = null;

    /** false tant que les données n'ont pas été chargées (rendu initial = squelette) */
    public bool $loaded = false;

    /**
     * Cache chaud : graphique complet dès la première réponse, sans squelette ni 2e requête.
     * Cache vide : squelette, puis load() via wire:init.
     */
    public function mount(): void
    {
        $this->month = date('m');
        $this->year  = date('Y');

        $cached = Cache::get($this->cacheKey());

        if (is_array($cached)) {
            $this->apply($cached);
            $this->loaded = true;
        }
    }

    /** Chargement différé (wire:init) */
    public function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->refreshChart();
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['month', 'year'], true)) {
            $this->refreshChart();
        }
    }

    /** $force = true : « Réessayer » / « Actualiser » (ignore le cache) */
    public function refreshChart(bool $force = false): void
    {
        $this->loadData($force);
        $this->loaded = true;

        $this->dispatch('user-activity-chart-updated', chart: $this->chart);
    }

    // Clé liée à la session : les droits de l'API dépendent du token de l'utilisateur
    private function cacheKey(): string
    {
        return 'cosmia.dash.useractivity.'.sha1((string) session('cosmia_token')).'.'.$this->month.'.'.$this->year;
    }

    private function apply(array $data): void
    {
        $this->chart = $data['chart'];
        $this->empty = $data['empty'];
    }

    private function loadData(bool $force = false): void
    {
        $this->error = null;

        // Valeurs modifiables depuis le navigateur : on n'envoie à l'API que des valeurs connues
        if (! array_key_exists($this->month, CosmiaApi::monthOptions())) {
            $this->month = 'all';
        }

        if (! array_key_exists($this->year, CosmiaApi::yearOptions())) {
            $this->year = 'all';
        }

        $key = $this->cacheKey();

        if (! $force && is_array($cached = Cache::get($key))) {
            $this->apply($cached);

            return;
        }

        try {
            $response = app(CosmiaApi::class)->post('/dash/getuseractivitysummary', [
                'month' => $this->month,
                'year'  => $this->year,
            ]);
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();
            $this->apply(['chart' => ['labels' => [], 'series' => []], 'empty' => true]);

            return;
        }

        $data = $this->buildData(collect($response['details'] ?? [])->values());

        // Les erreurs ne sont jamais mises en cache
        Cache::put($key, $data, self::CACHE_TTL);

        $this->apply($data);
    }

    private function buildData($rows): array
    {
        $agents = collect($rows->isEmpty() ? [] : array_keys($rows->first()))
            ->reject(fn ($key) => $key === 'date')
            ->values();

        return [
            'chart' => [
                'labels' => $rows->map(fn ($r) => $this->formatDate((string) ($r['date'] ?? '')))->all(),
                'series' => $agents->map(fn ($agent, $i) => [
                    'name'  => $agent,
                    'color' => self::PALETTE[$i % count(self::PALETTE)],
                    'data'  => $rows->map(fn ($r) => (int) ($r[$agent] ?? 0))->all(),
                ])->all(),
            ],
            'empty' => $rows->isEmpty(),
        ];
    }

    // "2026-08-19" => "19/08" ; autre format => tel quel
    private function formatDate(string $date): string
    {
        return Carbon::canBeCreatedFromFormat($date, 'Y-m-d')
            ? Carbon::createFromFormat('Y-m-d', $date)->format('d/m')
            : $date;
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
            <flux:heading size="lg">{{ __('Statistiques par personne') }}</flux:heading>

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
            <flux:select wire:model.live="month" :label="__('Mois')">
                @foreach (\App\Services\CosmiaApi::monthOptions() as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="year" :label="__('Année')">
                @foreach (\App\Services\CosmiaApi::yearOptions() as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($loaded)
            @if ($error)
                <flux:callout variant="danger" icon="exclamation-circle" :heading="$error">
                    <x-slot name="actions">
                        <flux:button size="sm" wire:click="refreshChart(true)">{{ __('Réessayer') }}</flux:button>
                    </x-slot>
                </flux:callout>
            @elseif ($empty)
                <flux:text class="py-2 text-center">{{ __('Aucune donnée disponible.') }}</flux:text>
            @endif
        @endif

        {{-- Conteneur relatif : le squelette (Livewire) recouvre le graphique (Alpine) --}}
        <div
            class="relative h-[32rem] transition-opacity"
            wire:loading.class="opacity-50"
            wire:target="month,year,refreshChart"
        >
            @if (! $loaded)
                <div class="absolute inset-0 z-10 flex animate-pulse items-end gap-2 rounded-lg bg-zinc-100 p-4 dark:bg-zinc-700/60">
                    @foreach ([40, 65, 50, 80, 60, 90, 70, 55, 75, 45] as $h)
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

                    const datasets = (series) => series.map(s => ({
                        label: s.name,
                        data: s.data,
                        backgroundColor: s.color,
                        borderWidth: 0,
                        maxBarThickness: 28,
                    }));

                    return {
                        init() {
                            const c = colors();
                            const initial = @js($chart);

                            chart = new Chart(this.$refs.canvas, {
                                type: 'bar',
                                data: { labels: initial.labels, datasets: datasets(initial.series) },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    interaction: { mode: 'index', intersect: false },
                                    plugins: {
                                        legend: { position: 'bottom', labels: { color: c.text, usePointStyle: true } },
                                        tooltip: { callbacks: { label: (ctx) => ' ' + ctx.dataset.label + ' : ' + ctx.parsed.y + ' Email' } },
                                    },
                                    scales: {
                                        x: { ticks: { color: c.text }, grid: { color: c.grid } },
                                        y: { beginAtZero: true, title: { display: true, text: 'Email(s)', color: c.text }, ticks: { color: c.text, precision: 0 }, grid: { color: c.grid } },
                                    },
                                },
                            });

                            // Données reçues avant la création du graphique
                            if (pending) {
                                this.update(pending);
                                pending = null;
                            }
                        },

                        update(data) {
                            if (! chart) {
                                pending = data;
                                return;
                            }

                            chart.data.labels = data.labels;
                            chart.data.datasets = datasets(data.series);
                            chart.update();
                        },

                        destroy() {
                            chart?.destroy();
                            chart = null;
                        },
                    };
                })()"
                x-on:user-activity-chart-updated.window="update($event.detail.chart)"
            >
                <canvas x-ref="canvas"></canvas>
            </div>
        </div>
    </flux:card>
</div>
