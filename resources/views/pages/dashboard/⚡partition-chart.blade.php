<?php

use App\Services\CosmiaApi;
use Carbon\Carbon;
use Livewire\Component;

new class extends Component
{
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
        'changement_adresse_livraison' => '#14b8a6', // était presque noir, illisible en mode sombre
        'inversion_colis'              => '#eab308',
    ];

    public const FALLBACK = ['#64748b', '#a855f7', '#84cc16', '#0ea5e9'];

    public array $projects = [];

    public string $projectId = 'all';

    public array $rows = [];

    public array $chart = ['labels' => [], 'series' => []];

    public int $total = 0;

    public ?string $error = null;

    public function mount(): void
    {
        try {
            $this->projects = app(CosmiaApi::class)->get('/project');
        } catch (\RuntimeException) {
            $this->projects = [];
        }

        $this->loadData();
    }

    public function setProject(string $id): void
    {
        $this->projectId = $id;
        $this->loadData();

        $this->dispatch('partition-chart-updated', chart: $this->chart);
    }

    public function loadData(): void
    {
        $this->error = null;

        try {
            $data = app(CosmiaApi::class)->post('/dash/getTicketPartitionSummary', [
                'month'      => 'all',
                'year'       => 'all',
                'project_id' => $this->projectId,
            ]);

            $this->rows = collect($data['details'] ?? [])->sortBy('date')->values()->all();
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();
            $this->rows  = [];
        }

        $this->buildChart();
    }

    private function buildChart(): void
    {
        $rows = collect($this->rows);

        $categories = collect($rows->isEmpty() ? [] : array_keys($rows->first()))
            ->reject(fn ($key) => $key === 'date')
            ->values();

        $this->chart = [
            'labels' => $rows->map(fn ($r) => Carbon::parse($r['date'])->format('d/m'))->all(),
            'series' => $categories->map(fn ($key, $i) => [
                'name'  => self::LABELS[$key] ?? $key,
                'color' => self::COLORS[$key] ?? self::FALLBACK[$i % count(self::FALLBACK)],
                'data'  => $rows->map(fn ($r) => (int) ($r[$key] ?? 0))->all(),
            ])->all(),
        ];

        $this->total = (int) collect($this->chart['series'])->sum(fn ($s) => array_sum($s['data']));
    }
};
?>

@assets
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
@endassets

<flux:card class="flex flex-col gap-4 bg-zinc-100 dark:bg-zinc-700/60 border-zinc-200 dark:border-zinc-600">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="lg">{{ __('Classification par catégorie') }}</flux:heading>
            <flux:text class="text-sm">
                {{ __('Total classifié :') }}
                <span class="font-semibold">{{ number_format($total, 0, ',', ' ') }}</span>
            </flux:text>
        </div>

        <flux:modal.trigger name="partition-table">
            <flux:button size="sm" icon="table-cells">{{ __('Mode Tableau') }}</flux:button>
        </flux:modal.trigger>
    </div>

    <div class="flex flex-wrap items-center gap-2">
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
                {{ $project['name'] }}
            </flux:button>
        @endforeach
    </div>

    @if ($error)
        <flux:callout variant="danger" icon="exclamation-circle" :heading="$error">
            <x-slot name="actions">
                <flux:button size="sm" wire:click="setProject('{{ $projectId }}')">{{ __('Réessayer') }}</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <div
        wire:ignore
        wire:loading.class="opacity-50"
        wire:target="setProject"
        class="relative h-96 transition-opacity"
        x-data="(() => {
            let chart;

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
                },

                update(data) {
                    chart.data.labels = data.labels;
                    chart.data.datasets = datasets(data.series);
                    chart.update();
                },
            };
        })()"
        x-on:partition-chart-updated.window="update($event.detail.chart)"
    >
        <canvas x-ref="canvas"></canvas>
    </div>

    {{-- Mode tableau --}}
    <flux:modal name="partition-table" class="w-full max-w-7xl">
        <flux:heading size="lg" class="mb-4">{{ __('Classification par catégorie — Mode Tableau') }}</flux:heading>

        @if (empty($rows))
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
                    @foreach ($rows as $i => $row)
                        <tr wire:key="pr-{{ $i }}" class="hover:bg-zinc-100 dark:hover:bg-zinc-700/50">
                            <td class="sticky left-0 whitespace-nowrap bg-white px-4 py-2 font-medium dark:bg-zinc-800">
                                {{ \Carbon\Carbon::parse($row['date'])->locale('fr')->translatedFormat('d M Y') }}
                            </td>
                            @foreach ($series as $s)
                                @php $value = $s['data'][$i] ?? 0; @endphp
                                <td class="px-4 py-2 text-center {{ $value > 0 ? 'font-medium text-blue-500' : 'text-zinc-400' }}">
                                    {{ $value }}
                                </td>
                            @endforeach
                            <td class="px-4 py-2 text-center font-bold">
                                {{ collect($series)->sum(fn ($s) => $s['data'][$i] ?? 0) }}
                            </td>
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
                <strong>{{ __('Période :') }}</strong> {{ count($rows) }} {{ __('jours') }}
            </flux:text>
        @endif
    </flux:modal>
</flux:card>
