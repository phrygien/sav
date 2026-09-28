<?php

use App\Services\CosmiaApi;
use Carbon\Carbon;
use Livewire\Component;

new class extends Component
{
    public const PALETTE = [
        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899',
        '#06b6d4', '#f97316', '#b8c925', '#14b8a6', '#eab308',
    ];

    public string $month = 'all';

    public string $year = 'all';

    public array $chart = ['labels' => [], 'series' => []];

    public bool $empty = true;

    public ?string $error = null;

    public function mount(): void
    {
        $this->month = date('m');
        $this->year  = date('Y');

        $this->loadData();
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['month', 'year'], true)) {
            $this->refreshChart();
        }
    }

    public function refreshChart(): void
    {
        $this->loadData();

        $this->dispatch('user-activity-chart-updated', chart: $this->chart);
    }

    public function loadData(): void
    {
        $this->error = null;

        try {
            $data = app(CosmiaApi::class)->post('/dash/getuseractivitysummary', [
                'month' => $this->month,
                'year'  => $this->year,
            ]);

            $rows = collect($data['details'] ?? [])->values();
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();
            $rows = collect();
        }

        $agents = collect($rows->isEmpty() ? [] : array_keys($rows->first()))
            ->reject(fn ($key) => $key === 'date')
            ->values();

        $this->chart = [
            'labels' => $rows->map(fn ($r) => Carbon::canBeCreatedFromFormat($r['date'], 'Y-m-d')
                ? Carbon::createFromFormat('Y-m-d', $r['date'])->format('d/m')
                : $r['date'])->all(),
            'series' => $agents->map(fn ($agent, $i) => [
                'name'  => $agent,
                'color' => self::PALETTE[$i % count(self::PALETTE)],
                'data'  => $rows->map(fn ($r) => (int) ($r[$agent] ?? 0))->all(),
            ])->all(),
        ];

        $this->empty = $rows->isEmpty();
    }
};
?>

@assets
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
@endassets

<flux:card class="flex flex-col gap-4 bg-zinc-100 dark:bg-zinc-700/60 border-zinc-200 dark:border-zinc-600">
    <flux:heading size="lg">{{ __('Statistiques par personne') }}</flux:heading>

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

    @if ($error)
        <flux:callout variant="danger" icon="exclamation-circle" :heading="$error">
            <x-slot name="actions">
                <flux:button size="sm" wire:click="refreshChart">{{ __('Réessayer') }}</flux:button>
            </x-slot>
        </flux:callout>
    @elseif ($empty)
        <flux:text class="py-2 text-center">{{ __('Aucune donnée disponible.') }}</flux:text>
    @endif

    <div
        wire:ignore
        wire:loading.class="opacity-50"
        wire:target="month,year"
        class="relative h-[32rem] transition-opacity"
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
                                tooltip: { callbacks: { label: (ctx) => ' ' + ctx.dataset.label + ' : ' + ctx.parsed.y + ' action(s)' } },
                            },
                            scales: {
                                x: { ticks: { color: c.text }, grid: { color: c.grid } },
                                y: { beginAtZero: true, title: { display: true, text: 'Action(s)', color: c.text }, ticks: { color: c.text, precision: 0 }, grid: { color: c.grid } },
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
        x-on:user-activity-chart-updated.window="update($event.detail.chart)"
    >
        <canvas x-ref="canvas"></canvas>
    </div>
</flux:card>
