<?php

use App\Services\CosmiaApi;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    // Cache court des statistiques (secondes) ; la liste des projets bouge peu
    private const CACHE_TTL          = 120;
    private const CACHE_PROJECTS_TTL = 600;

    public const LABELS = [
        'suivi_commande'               => 'Suivi commande',
        'colis_non_recu'               => 'Colis non reçu',
        'paiement'                     => 'Paiement',
        'facture_non_recu'             => 'Facture non reçue',
        'produit_defectueux'           => 'Produit défectueux',
        'retour_retractation'          => 'Retour/Rétractation',
        'demande_specifique'           => 'Demande spécifique',
        'colis_vide'                   => 'Colis vide',
        'spam'                         => 'Spam',
        'changement_adresse_livraison' => 'Changement Adresse',
        'inversion_colis'              => 'Inversion Colis',
    ];

    public const COLORS = [
        'suivi_commande'               => '#3b82f6',
        'colis_non_recu'               => '#ef4444',
        'paiement'                     => '#10b981',
        'facture_non_recu'             => '#f59e0b',
        'produit_defectueux'           => '#8b5cf6',
        'retour_retractation'          => '#ec4899',
        'demande_specifique'           => '#06b6d4',
        'colis_vide'                   => '#f97316',
        'spam'                         => '#b8c925',
        'changement_adresse_livraison' => '#14b8a6',
        'inversion_colis'              => '#eab308',
    ];

    public const FALLBACK = ['#64748b', '#a855f7', '#84cc16', '#0ea5e9'];

    public array $projects = [];

    // Verrouillé : ne change que via setProject(), qui valide la valeur
    #[Locked]
    public string $projectId = 'all';

    // Dates déjà formatées pour le tableau (remplace l'ancien tableau $rows complet)
    public array $dates = [];

    public array $chart = ['labels' => [], 'series' => []];

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

        $this->dispatch('partition-chart-updated', chart: $this->chart);
    }

    public function setProject(string $id, bool $force = false): void
    {
        $known = collect($this->projects)->pluck('id')->map(fn ($v) => (string) $v)->all();

        // Valeur modifiable depuis le navigateur : seule « all » ou un projet connu est acceptée
        if ($id !== 'all' && ! in_array($id, $known, true)) {
            return;
        }

        $this->projectId = $id;
        $this->loadData($force);

        $this->dispatch('partition-chart-updated', chart: $this->chart);
    }

    // Clé liée à la session : les droits de l'API dépendent du token de l'utilisateur
    private function cacheKey(?string $projectId = null): string
    {
        return 'cosmia.dash.partition.'.sha1((string) session('cosmia_token')).'.'.($projectId ?? $this->projectId);
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
            $this->projects = []; // les filtres sont masqués, le graphique reste utilisable

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
        $this->dates = $data['dates'];
        $this->total = $data['total'];
    }

    private function loadData(bool $force = false): void
    {
        $this->error = null;

        $key = $this->cacheKey();

        if (! $force && is_array($cached = Cache::get($key))) {
            $this->apply($cached);

            return;
        }

        try {
            $response = app(CosmiaApi::class)->post('/dash/getTicketPartitionSummary', [
                'month'      => 'all',
                'year'       => 'all',
                'project_id' => $this->projectId,
            ]);
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();
            $this->apply(['chart' => ['labels' => [], 'series' => []], 'dates' => [], 'total' => 0]);

            return;
        }

        $data = $this->buildData(collect($response['details'] ?? [])->sortBy('date')->values());

        // Les erreurs ne sont jamais mises en cache
        Cache::put($key, $data, self::CACHE_TTL);

        $this->apply($data);
    }

    private function buildData($rows): array
    {
        $categories = collect($rows->isEmpty() ? [] : array_keys($rows->first()))
            ->reject(fn ($key) => $key === 'date')
            ->values();

        $series = $categories->map(fn ($key, $i) => [
            'name'  => self::LABELS[$key] ?? $key,
            'color' => self::COLORS[$key] ?? self::FALLBACK[$i % count(self::FALLBACK)],
            'data'  => $rows->map(fn ($r) => (int) ($r[$key] ?? 0))->all(),
        ])->all();

        return [
            'chart' => [
                'labels' => $rows->map(fn ($r) => $this->safeDate($r['date'] ?? null, 'd/m'))->all(),
                'series' => $series,
            ],
            'dates' => $rows->map(fn ($r) => $this->safeDate($r['date'] ?? null, 'd M Y', true))->all(),
            'total' => (int) collect($series)->sum(fn ($s) => array_sum($s['data'])),
        ];
    }

    private function safeDate(?string $value, string $format, bool $translated = false): string
    {
        try {
            $c = Carbon::parse((string) $value)->locale('fr');

            return $translated ? $c->translatedFormat($format) : $c->format($format);
        } catch (\Throwable) {
            return (string) $value;
        }
    }
};
?>

@assets
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
@endassets

{{-- wire:init sur un <div> : Blade n'accepte pas @if dans les attributs d'un composant <flux:...> --}}
<div @if (! $loaded) wire:init="load" @endif>
    <flux:card class="flex flex-col gap-4 bg-zinc-100 dark:bg-zinc-700/60 border-zinc-200 dark:border-zinc-600">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ __('Classification par catégorie') }}</flux:heading>
                <flux:text class="text-sm">
                    {{ __('Total classifié :') }}
                    @if ($loaded)
                        <span class="font-semibold">{{ number_format($total, 0, ',', ' ') }}</span>
                    @else
                        <span class="inline-block h-4 w-10 animate-pulse rounded bg-zinc-200 align-middle dark:bg-zinc-600"></span>
                    @endif
                </flux:text>
            </div>

            <div class="flex items-center gap-2">
                @if ($loaded)
                    {{-- Les données sont gardées 2 min côté serveur : ce bouton force une mise à jour --}}
                    <flux:button
                        size="sm"
                        variant="ghost"
                        wire:click="setProject('{{ $projectId }}', true)"
                        wire:loading.attr="disabled"
                        wire:target="setProject"
                        :aria-label="__('Actualiser')"
                    >
                        <i class="hgi-stroke hgi-refresh" wire:loading.remove wire:target="setProject"></i>
                        <i class="hgi-stroke hgi-loading-03 animate-spin" wire:loading wire:target="setProject"></i>
                    </flux:button>
                @endif

                <flux:modal.trigger name="partition-table">
                    <flux:button size="sm" icon="table-cells" :disabled="! $loaded">{{ __('Mode Tableau') }}</flux:button>
                </flux:modal.trigger>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($loaded)
                <flux:button size="sm" :variant="$projectId === 'all' ? 'primary' : 'outline'" wire:click="setProject('all')">
                    {{ __('Tous les projets') }}
                </flux:button>

                @foreach ($projects as $project)
                    <flux:button
                        wire:key="pf-{{ $project['id'] }}"
                        size="sm"
                        :variant="(string) $projectId === (string) $project['id'] ? 'primary' : 'outline'"
                        wire:click="setProject('{{ $project['id'] }}')"
                    >
                        {{ $project['name'] ?? '' }}
                    </flux:button>
                @endforeach
            @else
                <div class="flex animate-pulse gap-2">
                    @foreach (range(1, 3) as $i)
                        <div wire:key="sk-filter-{{ $i }}" class="h-8 w-24 rounded-md bg-zinc-200 dark:bg-zinc-600"></div>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($error)
            <flux:callout variant="danger" icon="exclamation-circle" :heading="$error">
                <x-slot name="actions">
                    <flux:button size="sm" wire:click="setProject('{{ $projectId }}', true)">{{ __('Réessayer') }}</flux:button>
                </x-slot>
            </flux:callout>
        @endif

        {{-- Conteneur relatif : le squelette (Livewire) recouvre le graphique (Alpine) --}}
        <div
            class="relative h-96 transition-opacity"
            wire:loading.class="opacity-50"
            wire:target="setProject"
        >
            @if (! $loaded)
                <div class="absolute inset-0 z-10 flex animate-pulse items-end gap-2 rounded-lg bg-zinc-100 p-4 dark:bg-zinc-700/60">
                    @foreach ([40, 65, 50, 80, 60, 90, 70, 55] as $h)
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
                        borderColor: s.color,
                        backgroundColor: s.color,
                        borderWidth: 2,
                        tension: 0.3,
                        pointRadius: 2,
                    }));

                    return {
                        init() {
                            const c = colors();
                            const initial = @js($chart);

                            chart = new Chart(this.$refs.canvas, {
                                type: 'line',
                                data: { labels: initial.labels, datasets: datasets(initial.series) },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    interaction: { mode: 'index', intersect: false },
                                    plugins: {
                                        legend: { position: 'bottom', labels: { color: c.text, usePointStyle: true } },
                                        tooltip: {
                                            callbacks: {
                                                footer: (items) => 'Total : ' + items.reduce((sum, i) => sum + i.parsed.y, 0),
                                            },
                                        },
                                    },
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
                x-on:partition-chart-updated.window="update($event.detail.chart)"
            >
                <canvas x-ref="canvas"></canvas>
            </div>
        </div>

        {{-- Mode tableau --}}
        <flux:modal name="partition-table" class="w-full max-w-7xl">
            <flux:heading size="lg" class="mb-4">{{ __('Classification par catégorie — Mode Tableau') }}</flux:heading>

            @if (empty($dates))
                <flux:text class="py-6 text-center">{{ __('Aucune donnée disponible.') }}</flux:text>
            @else
                @php $series = $chart['series']; @endphp

                <div class="max-h-[60vh] overflow-auto">
                    <table class="min-w-full border-collapse text-sm">
                        <thead class="sticky top-0 z-10 bg-zinc-100 dark:bg-zinc-800">
                        <tr>
                            <th class="sticky left-0 z-20 bg-zinc-100 px-4 py-3 text-left font-semibold dark:bg-zinc-800">Date</th>
                            @foreach ($series as $s)
                                <th class="whitespace-nowrap px-4 py-3 text-center font-semibold">{{ $s['name'] }}</th>
                            @endforeach
                            <th class="px-4 py-3 text-center font-semibold">Total</th>
                        </tr>
                        </thead>

                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($dates as $i => $date)
                            <tr wire:key="pr-{{ $i }}" class="hover:bg-zinc-100 dark:hover:bg-zinc-700/50">
                                <td class="sticky left-0 whitespace-nowrap bg-white px-4 py-2 font-medium dark:bg-zinc-800">{{ $date }}</td>
                                @php $rowTotal = 0; @endphp
                                @foreach ($series as $s)
                                    @php
                                        $value = $s['data'][$i] ?? 0;
                                        $rowTotal += $value;
                                    @endphp
                                    <td class="px-4 py-2 text-center {{ $value > 0 ? 'font-medium text-blue-500' : 'text-zinc-400' }}">{{ $value }}</td>
                                @endforeach
                                <td class="px-4 py-2 text-center font-bold">{{ $rowTotal }}</td>
                            </tr>
                        @endforeach
                        </tbody>

                        <tfoot class="sticky bottom-0 bg-zinc-100 font-bold dark:bg-zinc-800">
                        <tr>
                            <td class="sticky left-0 bg-zinc-100 px-4 py-3 dark:bg-zinc-800">Total</td>
                            @foreach ($series as $s)
                                <td class="px-4 py-3 text-center text-blue-500">{{ array_sum($s['data']) }}</td>
                            @endforeach
                            <td class="px-4 py-3 text-center">{{ $total }}</td>
                        </tr>
                        </tfoot>
                    </table>
                </div>

                <flux:text class="mt-4 text-sm">
                    <strong>{{ __('Période :') }}</strong> {{ count($dates) }} {{ __('jours') }}
                </flux:text>
            @endif
        </flux:modal>
    </flux:card>
</div>
