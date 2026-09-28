<?php

use App\Services\CosmiaApi;
use Livewire\Component;

new class extends Component
{
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

    public function mount(): void
    {
        $this->month = date('m');
        $this->year  = date('Y');

        try {
            $this->projects = app(CosmiaApi::class)->get('/project');
        } catch (\RuntimeException) {
            $this->projects = [];
        }

        $this->loadData();
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['month', 'year', 'projectId'], true)) {
            $this->refreshChart();
        }
    }

    public function refreshChart(): void
    {
        $this->loadData();

        $this->dispatch('donut-chart-updated', chart: $this->chart);
    }

    public function loadData(): void
    {
        $this->error = null;

        try {
            $data = app(CosmiaApi::class)->post('/dash/getdonutSummary', [
                'month'      => $this->month,
                'year'       => $this->year,
                'project_id' => $this->projectId === 'all' ? null : $this->projectId,
            ]);

            $details = collect($data['details'] ?? []);

            $this->chart = [
                'labels' => $details->map(fn ($d) => $d['label_name'].' ('.(int) $d['nb'].')')->all(),
                'values' => $details->map(fn ($d) => (int) $d['nb'])->all(),
            ];
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();
            $this->chart = ['labels' => [], 'values' => []];
        }

        $this->total = array_sum($this->chart['values']);
    }
};
?>

@assets
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
@endassets

<flux:card class="flex flex-col gap-4 bg-zinc-100 dark:bg-zinc-700/60 border-zinc-200 dark:border-zinc-600">
    <flux:heading size="lg">{{ __('Répartition selon le type de demande') }}</flux:heading>

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
                <flux:select.option :value="$project['id']">{{ $project['name'] }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($error)
        <flux:callout variant="danger" icon="exclamation-circle" :heading="$error">
            <x-slot name="actions">
                <flux:button size="sm" wire:click="refreshChart">{{ __('Réessayer') }}</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <div class="text-center">
        <div class="text-4xl font-bold text-red-500">{{ number_format($total, 0, ',', ' ') }}</div>
        <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total demandes') }}</div>
    </div>

    <div
        wire:ignore
        wire:loading.class="opacity-50"
        wire:target="month,year,projectId"
        class="relative h-96 transition-opacity"
        x-data="(() => {
            let chart;
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
                },

                update(data) {
                    chart.data.labels = data.labels;
                    chart.data.datasets[0].data = data.values;
                    chart.update();
                },
            };
        })()"
        x-on:donut-chart-updated.window="update($event.detail.chart)"
    >
        <canvas x-ref="canvas"></canvas>
    </div>
</flux:card>
