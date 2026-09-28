<?php

use App\Services\CosmiaApi;
use Livewire\Component;

new class extends Component
{
    public const METRICS = [
        'total_action'       => ['label' => 'Total', 'color' => 'text-zinc-500 dark:text-zinc-400'],
        'answer_mail'        => ['label' => 'Mail',  'color' => 'text-sky-500'],
        'mark_as_read'       => ['label' => 'Marq.', 'color' => 'text-zinc-500 dark:text-zinc-400'],
        'pending_ticket'     => ['label' => 'Atte.', 'color' => 'text-zinc-500 dark:text-zinc-400'],
        'in_progress_ticket' => ['label' => 'Prog.', 'color' => 'text-zinc-500 dark:text-zinc-400'],
        'closed_ticket'      => ['label' => 'Clot.', 'color' => 'text-zinc-500 dark:text-zinc-400'],
    ];

    public string $dateStart = '';

    public string $dateEnd = '';

    public array $rows = [];

    public array $agents = [];

    public ?string $error = null;

    public function mount(): void
    {
        $this->dateEnd   = now()->format('Y-m-d');
        $this->dateStart = now()->subDays(30)->format('Y-m-d');

        $this->loadData();
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['dateStart', 'dateEnd'], true)) {
            $this->loadData();
        }
    }

    public function loadData(): void
    {
        $this->error = null;

        try {
            $data = app(CosmiaApi::class)->post('/dash/getuseractivitysummary2', [
                'date_start' => $this->dateStart,
                'date_end'   => $this->dateEnd,
            ]);

            $this->rows = collect($data['details'] ?? [])->values()->all();
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();
            $this->rows  = [];
        }

        $this->agents = collect($this->rows[0] ?? [])
            ->keys()
            ->reject(fn ($key) => $key === 'date')
            ->values()
            ->all();
    }
};
?>

<flux:card class="flex flex-col gap-4 bg-zinc-100 dark:bg-zinc-700/60 border-zinc-200 dark:border-zinc-600">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="lg">{{ __('Résumé Activité Utilisateur') }}</flux:heading>
            <flux:text class="text-sm">{{ __('Résumé de chaque action utilisateur') }}</flux:text>
        </div>

        <div class="flex items-end gap-3">
            <flux:input type="date" wire:model.live="dateStart" :label="__('Date de début')" />
            <flux:input type="date" wire:model.live="dateEnd" :label="__('Date de fin')" />
        </div>
    </div>

    <div class="flex flex-wrap gap-x-6 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
        <span><strong>Mail :</strong> {{ __('Envoyer un mail au client') }}</span>
        <span><strong>Marq. :</strong> {{ __('Marquer comme lu un mail') }}</span>
        <span><strong>Atte. :</strong> {{ __('Remettre en attente un ticket') }}</span>
        <span><strong>Prog. :</strong> {{ __('Mettre en cours un ticket') }}</span>
        <span><strong>Clot. :</strong> {{ __('Clôturer un ticket') }}</span>
    </div>

    @if ($error)
        <flux:callout variant="danger" icon="exclamation-circle" :heading="$error">
            <x-slot name="actions">
                <flux:button size="sm" wire:click="loadData">{{ __('Réessayer') }}</flux:button>
            </x-slot>
        </flux:callout>
    @elseif (empty($rows))
        <flux:text class="py-12 text-center">{{ __('Aucune donnée disponible pour cette période.') }}</flux:text>
    @else
        @php
            $metrics = $this::METRICS;
            $border  = 'border-zinc-300 dark:border-zinc-600';
            $solid   = 'bg-zinc-100 dark:bg-zinc-800';
        @endphp

        {{-- La clé force un nouvel élément à chaque changement de période : le scroll repart en bas --}}
        <div
            wire:key="ua-{{ $dateStart }}-{{ $dateEnd }}"
            wire:loading.class="opacity-50"
            wire:target="dateStart,dateEnd"
            x-data
            x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
            class="max-h-[625px] overflow-auto transition-opacity"
        >
            <table class="w-full border-collapse text-xs">
                <thead class="sticky top-0 z-30 {{ $solid }}">
                <tr>
                    <th rowspan="2" class="sticky left-0 z-40 {{ $solid }} min-w-[52px] whitespace-nowrap border-b border-r-2 {{ $border }} px-4 py-2.5 text-left text-[10px] uppercase tracking-widest">
                        Date
                    </th>
                    @foreach ($agents as $agent)
                        <th colspan="{{ count($metrics) }}" class="{{ $solid }} whitespace-nowrap border-b border-l-2 {{ $border }} px-2 pb-1.5 pt-2.5 text-center text-[13px] font-bold">
                            {{ $agent }}
                        </th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($agents as $agent)
                        @foreach ($metrics as $meta)
                            <th class="{{ $solid }} border-b-2 {{ $loop->first ? 'border-l-2' : 'border-l' }} {{ $border }} px-1.5 pb-2 pt-1 text-center text-[10px] font-semibold uppercase tracking-wider {{ $meta['color'] }}">
                                {{ $meta['label'] }}
                            </th>
                        @endforeach
                    @endforeach
                </tr>
                </thead>

                <tbody>
                @foreach ($rows as $i => $row)
                    @php
                        $date = \Carbon\Carbon::parse($row['date']);
                        $hasActivity = collect($agents)->contains(fn ($a) => (int) ($row[$a]['total_action'] ?? 0) > 0);
                    @endphp
                    <tr wire:key="ua-row-{{ $i }}" class="border-b {{ $border }} transition-colors hover:bg-zinc-200 dark:hover:bg-zinc-700 {{ $hasActivity ? '' : 'opacity-60' }}">
                        <td class="sticky left-0 z-10 {{ $solid }} whitespace-nowrap border-r-2 {{ $border }} px-4 py-2 text-center">
                            <span class="block text-base font-bold leading-none">{{ $date->format('d') }}</span>
                            <span class="mt-0.5 block text-[9px] uppercase tracking-widest">{{ $date->locale('fr')->translatedFormat('M') }}</span>
                        </td>

                        @foreach ($agents as $agent)
                            @foreach ($metrics as $key => $meta)
                                @php $val = (int) ($row[$agent][$key] ?? 0); @endphp
                                <td class="px-1.5 py-2 text-center tabular-nums {{ $loop->first ? 'border-l-2' : 'border-l' }} {{ $border }} {{ $val > 0 ? 'font-bold '.$meta['color'] : 'text-zinc-400 dark:text-zinc-600' }}">
                                    {{ $val > 0 ? $val : '—' }}
                                </td>
                            @endforeach
                        @endforeach
                    </tr>
                @endforeach
                </tbody>

                <tfoot class="sticky bottom-0 z-30 {{ $solid }}">
                <tr>
                    <td class="sticky left-0 z-40 {{ $solid }} border-r-2 border-t-2 {{ $border }} px-4 py-2.5 text-center text-[10px] font-extrabold uppercase tracking-widest">
                        Total
                    </td>
                    @foreach ($agents as $agent)
                        @foreach ($metrics as $key => $meta)
                            @php $sum = collect($rows)->sum(fn ($r) => (int) ($r[$agent][$key] ?? 0)); @endphp
                            <td class="{{ $solid }} border-t-2 px-1.5 py-2.5 text-center tabular-nums {{ $loop->first ? 'border-l-2' : 'border-l' }} {{ $border }} {{ $sum > 0 ? 'text-sm font-bold '.$meta['color'] : 'text-zinc-400 dark:text-zinc-600' }}">
                                {{ $sum > 0 ? $sum : '—' }}
                            </td>
                        @endforeach
                    @endforeach
                </tr>
                </tfoot>
            </table>
        </div>
    @endif
</flux:card>
