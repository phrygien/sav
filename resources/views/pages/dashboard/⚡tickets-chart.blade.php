<?php

use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

new class extends Component
{
    // Valeur envoyée à l'API (date_range) => libellé affiché
    public const RANGES = [
        0 => '7 Jours',
        1 => '15 Jours',
        2 => '1 Mois',
        3 => '3 Mois',
        4 => '6 Mois',
        5 => '1 Ans',
    ];

    // Valeur envoyée à l'API (ticket_status) => libellé affiché
    public const STATUSES = [
        'all'        => 'Tous les statuts',
        'en attente' => 'En attente',
        'en cours'   => 'En cours',
        'cloture'    => 'Clôturé',
    ];

    public int $range = 0;

    public string $status = 'all';

    public int $total = 0;

    public array $labels = [];

    public array $values = [];

    public ?string $error = null;

    public function mount(): void
    {
        $this->loadData();
    }

    public function updatedRange(): void
    {
        $this->refreshChart();
    }

    public function updatedStatus(): void
    {
        $this->refreshChart();
    }

    public function refreshChart(): void
    {
        $this->loadData();

        $this->dispatch('tickets-chart-updated', labels: $this->labels, values: $this->values);
    }

    public function loadData(): void
    {
        $this->error = null;

        try {
            $request = Http::baseUrl(config('services.cosmia.url'))
                ->withHeaders(['x-secret-key' => config('services.cosmia.secret')])
                ->acceptJson()
                ->asJson()
                ->timeout(15);

            if ($token = session('cosmia_token')) {
                $request = $request->withToken($token);
            }

            $response = $request->post('/dash/getticketsummary', [
                'ticket_status' => $this->status === 'all' ? null : $this->status,
                'date_range'    => $this->range,
            ]);
        } catch (ConnectionException) {
            $this->fail(__('Service indisponible, réessaie dans un instant.'));

            return;
        }

        if ($response->failed()) {
            $this->fail(
                $response->json('error')
                ?? $response->json('message')
                ?? __('Impossible de charger les statistiques.')
            );

            return;
        }

        $details = collect($response->json('details', []))->sortBy('period')->values();

        $this->total  = (int) $response->json('total', 0);
        $this->labels = $details->map(fn ($d) => $this->formatPeriod($d['period']))->all();
        $this->values = $details->map(fn ($d) => (int) $d['nombre'])->all();
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

<flux:card class="flex flex-col gap-4 bg-zinc-100 dark:bg-zinc-700/60 border-zinc-200 dark:border-zinc-600">
    <div>
        <flux:heading size="lg">{{ __('Tickets') }}</flux:heading>
        <flux:text class="text-sm">
            {{ __('Total sur la période :') }}
            <span class="font-semibold">{{ number_format($total, 0, ',', ' ') }}</span>
        </flux:text>
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
                <flux:button size="sm" wire:click="refreshChart">{{ __('Réessayer') }}</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <div
        wire:loading.class="opacity-50"
        wire:target="range,status"
        class="relative h-72 transition-opacity"
        wire:ignore
        x-data="(() => {
            let chart;

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
                },

                update(detail) {
                    chart.data.labels = detail.labels;
                    chart.data.datasets[0].data = detail.values;
                    chart.update();
                },
            };
        })()"
        x-on:tickets-chart-updated.window="update($event.detail)"
    >
        <canvas x-ref="canvas"></canvas>
    </div>
</flux:card>
