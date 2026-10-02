<?php

use App\Services\CosmiaApi;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

new class extends Component
{
    // Cache court des statistiques (secondes) ; la liste des projets bouge peu
    private const CACHE_TTL          = 120;
    private const CACHE_PROJECTS_TTL = 600;

    public const PALETTE = [
        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899',
        '#06b6d4', '#f97316', '#b8c925', '#14b8a6', '#eab308',
    ];

    public array $projects = [];

    public string $month = 'all';

    public string $year = 'all';

    public string $projectId = 'all';

    public array $chart = ['labels' => [], 'values' => []];

    public int $total = 0;

    public ?string $error = null;

    /** false tant que les données n'ont pas été chargées (rendu initial = squelette) */
    public bool $loaded = false;

    /**
     * Cache chaud : rendu complet immédiat, sans squelette ni 2e requête.
     * Cache vide : squelette, puis load() via wire:init.
     */
    public function mount(): void
    {
        $this->month = date('m');
        $this->year  = date('Y');

        $projects = Cache::get('cosmia.projects');
        $data     = Cache::get($this->cacheKey());

        if (is_array($projects) && is_array($data)) {
            $this->projects = $projects;
            $this->apply($data);
            $this->loaded = true;
        }
    }

    /** Chargement différé (wire:init) */
    public function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loadProjects();
        $this->loadData();
        $this->loaded = true;

        $this->dispatch('donut-chart-updated', chart: $this->chart);
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['month', 'year', 'projectId'], true)) {
            $this->refreshChart();
        }
    }

    /** $force = true : « Réessayer » / « Actualiser » (ignore le cache) */
    public function refreshChart(bool $force = false): void
    {
        $this->loadData($force);

        $this->dispatch('donut-chart-updated', chart: $this->chart);
    }

    // Clé liée à la session : les droits de l'API dépendent du token de l'utilisateur
    private function cacheKey(): string
    {
        return 'cosmia.dash.donut.'.sha1((string) session('cosmia_token'))
            .'.'.$this->month.'.'.$this->year.'.'.$this->projectId;
    }

    private function loadProjects(): void
    {
        $cached = Cache::get('cosmia.projects');

        if (is_array($cached)) {
            $this->projects = $cached;

            return;
        }

        try {
            $response = app(CosmiaApi::class)->get('/project');
        } catch (\RuntimeException) {
            $this->projects = []; // le filtre projet reste sur « Tous », le graphique reste utilisable

            return;
        }

        $list = $response['data'] ?? $response;
        $list = array_is_list($list) ? $list : [];

        // Les erreurs ne sont jamais mises en cache
        Cache::put('cosmia.projects', $list, self::CACHE_PROJECTS_TTL);

        $this->projects = $list;
    }

    private function apply(array $data): void
    {
        $this->chart = $data['chart'];
        $this->total = $data['total'];
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

        $known = collect($this->projects)->pluck('id')->map(fn ($v) => (string) $v)->all();

        if ($this->projectId !== 'all' && ! in_array($this->projectId, $known, true)) {
            $this->projectId = 'all';
        }

        $key = $this->cacheKey();

        if (! $force && is_array($cached = Cache::get($key))) {
            $this->apply($cached);

            return;
        }

        try {
            $response = app(CosmiaApi::class)->post('/dash/getdonutSummary', [
                'month'      => $this->month,
                'year'       => $this->year,
                'project_id' => $this->projectId === 'all' ? null : $this->projectId,
            ]);
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();
            $this->apply(['chart' => ['labels' => [], 'values' => []], 'total' => 0]);

            return;
        }

        $details = collect($response['details'] ?? []);

        $data = [
            'chart' => [
                'labels' => $details->map(fn ($d) => ($d['label_name'] ?? '-').' ('.(int) ($d['nb'] ?? 0).')')->all(),
                'values' => $details->map(fn ($d) => (int) ($d['nb'] ?? 0))->all(),
            ],
            'total' => (int) $details->sum(fn ($d) => (int) ($d['nb'] ?? 0)),
        ];

        // Les erreurs ne sont jamais mises en cache
        Cache::put($key, $data, self::CACHE_TTL);

        $this->apply($data);
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
            <flux:heading size="lg">{{ __('Répartition selon le type de demande') }}</flux:heading>

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

        <div class="grid gap-4 md:grid-cols-3">
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

            <flux:select wire:model.live="projectId" :label="__('Par projet')">
                <flux:select.option value="all">{{ __('Tous') }}</flux:select.option>
                @foreach ($projects as $project)
                    <flux:select.option :value="$project['id']">{{ $project['name'] ?? '' }}</flux:select.option>
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

        <div class="text-center">
            @if ($loaded)
                <div class="text-4xl font-bold text-red-500">{{ number_format($total, 0, ',', ' ') }}</div>
            @else
                <div class="mx-auto h-10 w-20 animate-pulse rounded bg-zinc-200 dark:bg-zinc-600"></div>
            @endif
            <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total demandes') }}</div>
        </div>

        {{-- Conteneur relatif : le squelette (Livewire) recouvre le graphique (Alpine) --}}
        <div
            class="relative h-96 transition-opacity"
            wire:loading.class="opacity-50"
            wire:target="month,year,projectId,refreshChart"
        >
            @if (! $loaded)
                <div class="absolute inset-0 z-10 flex animate-pulse items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700/60">
                    <div class="size-56 rounded-full border-[2.5rem] border-zinc-200 dark:border-zinc-600"></div>
                </div>
            @endif

            <div
                wire:ignore
                class="h-full"
                x-data="(() => {
                    let chart = null;
                    let pending = null;
                    const palette = @js($this::PALETTE);

                    const textColor = () => document.documentElement.classList.contains('dark') ? '#a1a1aa' : '#52525b';

                    return {
                        init() {
                            const initial = @js($chart);

                            chart = new Chart(this.$refs.canvas, {
                                type: 'doughnut',
                                data: {
                                    labels: initial.labels,
                                    datasets: [{ data: initial.values, backgroundColor: palette, borderWidth: 0 }],
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    cutout: '55%',
                                    plugins: {
                                        legend: { position: 'bottom', labels: { color: textColor(), usePointStyle: true } },
                                        tooltip: {
                                            callbacks: {
                                                label: (ctx) => {
                                                    const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                                    const pct = total ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                                                    return ' ' + ctx.parsed + ' (' + pct + '%)';
                                                },
                                            },
                                        },
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
                            chart.data.datasets[0].data = data.values;
                            chart.update();
                        },

                        destroy() {
                            chart?.destroy();
                            chart = null;
                        },
                    };
                })()"
                x-on:donut-chart-updated.window="update($event.detail.chart)"
            >
                <canvas x-ref="canvas"></canvas>
            </div>
        </div>
    </flux:card>
</div>
