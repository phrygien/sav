<?php

use App\Services\CosmiaApi;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

new class extends Component
{
    // Cache court (secondes) : l'activité du jour évolue, mais pas à chaque seconde
    private const CACHE_TTL = 120;

    // Période maximale acceptée (jours) : protège l'API contre des requêtes énormes
    private const MAX_DAYS = 366;

    // 'hint' alimente la légende ; 'color' colore l'en-tête et les valeurs
    public const METRICS = [
        'total_action'       => ['label' => 'Total', 'hint' => null,                                'color' => 'text-zinc-600 dark:text-zinc-300'],
        'answer_mail'        => ['label' => 'Mail',  'hint' => 'Envoyer un mail au client',         'color' => 'text-sky-600 dark:text-sky-400'],
        'mark_as_read'       => ['label' => 'Marq.', 'hint' => 'Marquer comme lu un mail',          'color' => 'text-violet-600 dark:text-violet-400'],
        'pending_ticket'     => ['label' => 'Atte.', 'hint' => 'Remettre en attente un ticket',     'color' => 'text-amber-600 dark:text-amber-400'],
        'in_progress_ticket' => ['label' => 'Prog.', 'hint' => 'Mettre en cours un ticket',         'color' => 'text-blue-600 dark:text-blue-400'],
        'closed_ticket'      => ['label' => 'Clot.', 'hint' => 'Clôturer un ticket',                'color' => 'text-emerald-600 dark:text-emerald-400'],
    ];

    public string $dateStart = '';

    public string $dateEnd = '';

    /** @var array<int, string> noms des agents (colonnes) */
    public array $agents = [];

    /**
     * Lignes déjà mises en forme : ['day' => '05', 'month' => 'Sep', 'active' => bool, 'cells' => int[]]
     * 'cells' est à plat : index = indexAgent * nbMetriques + indexMetrique
     */
    public array $rows = [];

    /** Totaux par colonne, à plat (même ordre que 'cells') */
    public array $totals = [];

    public ?string $error = null;

    /** false tant que les données n'ont pas été chargées (rendu initial = squelette) */
    public bool $loaded = false;

    /**
     * Cache chaud : tableau complet dès la première réponse, sans squelette ni 2e requête.
     * Cache vide : squelette, puis load() via wire:init.
     */
    public function mount(): void
    {
        $this->dateEnd   = now()->format('Y-m-d');
        $this->dateStart = now()->subDays(30)->format('Y-m-d');

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

        $this->loadData();
        $this->loaded = true;
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['dateStart', 'dateEnd'], true)) {
            $this->loadData();
            $this->loaded = true;
        }
    }

    /** $force = true : « Réessayer » / « Actualiser » (ignore le cache) */
    public function refresh(): void
    {
        $this->loadData(true);
        $this->loaded = true;
    }

    // Clé liée à la session : les droits de l'API dépendent du token de l'utilisateur
    private function cacheKey(): string
    {
        return 'cosmia.dash.activity.'.sha1((string) session('cosmia_token')).'.'.$this->dateStart.'.'.$this->dateEnd;
    }

    private function apply(array $data): void
    {
        $this->agents = $data['agents'];
        $this->rows   = $data['rows'];
        $this->totals = $data['totals'];
    }

    /** Dates modifiables depuis le navigateur : on n'envoie à l'API que des dates valides et bornées */
    private function normalizeDates(): void
    {
        $parse = function (string $value): ?Carbon {
            try {
                $date = Carbon::createFromFormat('!Y-m-d', $value);

                return $date && $date->format('Y-m-d') === $value ? $date : null;
            } catch (\Throwable) {
                return null;
            }
        };

        $end   = $parse($this->dateEnd) ?? now()->startOfDay();
        $start = $parse($this->dateStart) ?? $end->copy()->subDays(30);

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        if ($start->diffInDays($end) > self::MAX_DAYS) {
            $start = $end->copy()->subDays(self::MAX_DAYS);
        }

        $this->dateStart = $start->format('Y-m-d');
        $this->dateEnd   = $end->format('Y-m-d');
    }

    private function loadData(bool $force = false): void
    {
        $this->error = null;

        $this->normalizeDates();

        $key = $this->cacheKey();

        if (! $force && is_array($cached = Cache::get($key))) {
            $this->apply($cached);

            return;
        }

        try {
            $response = app(CosmiaApi::class)->post('/dash/getuseractivitysummary2', [
                'date_start' => $this->dateStart,
                'date_end'   => $this->dateEnd,
            ]);
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();
            $this->apply(['agents' => [], 'rows' => [], 'totals' => []]);

            return;
        }

        $data = $this->buildData(collect($response['details'] ?? [])->values()->all());

        // Les erreurs ne sont jamais mises en cache
        Cache::put($key, $data, self::CACHE_TTL);

        $this->apply($data);
    }

    /** Toute la mise en forme (dates, sommes, activité) est faite ici, une seule fois */
    private function buildData(array $details): array
    {
        $agents = collect($details[0] ?? [])
            ->keys()
            ->reject(fn ($key) => $key === 'date')
            ->values()
            ->all();

        $metricKeys = array_keys(self::METRICS);
        $totals     = array_fill(0, count($agents) * count($metricKeys), 0);
        $rows       = [];

        foreach ($details as $row) {
            try {
                $date = Carbon::parse((string) ($row['date'] ?? ''))->locale('fr');
                $day  = $date->format('d');
                $mon  = $date->translatedFormat('M');
            } catch (\Throwable) {
                $day = '—';
                $mon = '';
            }

            $cells  = [];
            $active = false;

            foreach ($agents as $a => $agent) {
                foreach ($metricKeys as $m => $metric) {
                    $val     = (int) ($row[$agent][$metric] ?? 0);
                    $cells[] = $val;

                    $totals[$a * count($metricKeys) + $m] += $val;

                    if ($metric === 'total_action' && $val > 0) {
                        $active = true;
                    }
                }
            }

            $rows[] = ['day' => $day, 'month' => $mon, 'active' => $active, 'cells' => $cells];
        }

        return ['agents' => $agents, 'rows' => $rows, 'totals' => $totals];
    }
};
?>

{{-- wire:init sur un <div> : Blade n'accepte pas @if dans les attributs d'un composant <flux:...> --}}
<div @if (! $loaded) wire:init="load" @endif>
    <flux:card class="flex flex-col gap-0 overflow-hidden p-0 bg-white dark:bg-zinc-800 border-zinc-200 dark:border-zinc-600">
        {{-- En-tête séparé du contenu par une bordure --}}
        <div class="flex flex-wrap items-end justify-between gap-4 border-b border-zinc-200 px-6 py-4 dark:border-zinc-600">
            <div>
                <flux:heading size="lg">{{ __('Résumé Activité Utilisateur') }}</flux:heading>
                <flux:text class="text-sm">{{ __('Résumé de chaque action utilisateur') }}</flux:text>
            </div>

            <div class="flex items-end gap-3">
                <flux:input type="date" wire:model.live="dateStart" :label="__('Date de début')" />
                <flux:input type="date" wire:model.live="dateEnd" :label="__('Date de fin')" />

                {{-- Les données sont gardées 2 min côté serveur : ce bouton force une mise à jour --}}
                @if ($loaded)
                    <flux:button
                        size="sm"
                        variant="ghost"
                        wire:click="refresh"
                        wire:loading.attr="disabled"
                        wire:target="refresh"
                        :aria-label="__('Actualiser')"
                    >
                        <i class="hgi-stroke hgi-refresh" wire:loading.remove wire:target="refresh"></i>
                        <i class="hgi-stroke hgi-loading-03 animate-spin" wire:loading wire:target="refresh"></i>
                    </flux:button>
                @endif
            </div>
        </div>

        <div class="flex flex-col gap-4 p-6">
            {{-- Légende : une pastille colorée par action --}}
            <div class="flex flex-wrap gap-x-5 gap-y-2 text-xs text-zinc-500 dark:text-zinc-400">
                @foreach ($this::METRICS as $meta)
                    @if ($meta['hint'])
                        <span class="inline-flex items-center gap-1.5">
                            <span class="font-semibold {{ $meta['color'] }}">{{ $meta['label'] }}</span>
                            <span>{{ __($meta['hint']) }}</span>
                        </span>
                    @endif
                @endforeach
            </div>

            @if (! $loaded)
                {{-- Squelette pendant le chargement initial --}}
                <div class="animate-pulse space-y-2">
                    <div class="h-12 rounded bg-zinc-200 dark:bg-zinc-600"></div>
                    @foreach (range(1, 8) as $i)
                        <div wire:key="sk-ua-{{ $i }}" class="h-9 rounded bg-zinc-200/70 dark:bg-zinc-600/60"></div>
                    @endforeach
                </div>
            @elseif ($error)
                <flux:callout variant="danger" icon="exclamation-circle" :heading="$error">
                    <x-slot name="actions">
                        <flux:button size="sm" wire:click="refresh">{{ __('Réessayer') }}</flux:button>
                    </x-slot>
                </flux:callout>
            @elseif (empty($rows))
                <flux:text class="py-12 text-center">{{ __('Aucune donnée disponible pour cette période.') }}</flux:text>
            @else
                @php
                    $metrics = $this::METRICS;
                    $nb      = count($metrics);
                    $border  = 'border-zinc-200 dark:border-zinc-700';
                    $head    = 'bg-zinc-50 dark:bg-zinc-900';
                    $body    = 'bg-white dark:bg-zinc-800';
                @endphp

                {{-- La clé force un nouvel élément à chaque changement de période : le scroll repart en bas --}}
                <div
                    wire:key="ua-{{ $dateStart }}-{{ $dateEnd }}"
                    wire:loading.class="opacity-50"
                    wire:target="dateStart,dateEnd,refresh"
                    x-data
                    x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
                    class="max-h-[625px] overflow-auto rounded-lg border {{ $border }} transition-opacity"
                >
                    <table class="w-full border-separate border-spacing-0 text-xs">
                        <thead class="sticky top-0 z-30">
                        <tr>
                            <th rowspan="2" class="sticky left-0 z-40 {{ $head }} min-w-[64px] whitespace-nowrap border-b border-r {{ $border }} px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">
                                Date
                            </th>
                            @foreach ($agents as $agent)
                                <th colspan="{{ $nb }}" class="{{ $head }} whitespace-nowrap border-b border-l {{ $border }} px-2 py-2.5 text-center text-[13px] font-semibold text-zinc-900 dark:text-white">
                                    {{ $agent }}
                                </th>
                            @endforeach
                        </tr>
                        <tr>
                            @foreach ($agents as $agent)
                                @foreach ($metrics as $meta)
                                    <th class="{{ $head }} border-b {{ $loop->first ? 'border-l' : '' }} {{ $border }} px-2 pb-2 pt-1 text-center text-[10px] font-semibold uppercase tracking-wider {{ $meta['color'] }}">
                                        {{ $meta['label'] }}
                                    </th>
                                @endforeach
                            @endforeach
                        </tr>
                        </thead>

                        <tbody>
                        @foreach ($rows as $i => $row)
                            <tr wire:key="ua-row-{{ $i }}" class="group {{ $row['active'] ? '' : 'opacity-50' }}">
                                <td class="sticky left-0 z-10 {{ $body }} whitespace-nowrap border-b border-r {{ $border }} px-4 py-2 text-center group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700">
                                    <span class="block text-base font-semibold leading-none text-zinc-900 dark:text-white">{{ $row['day'] }}</span>
                                    <span class="mt-0.5 block text-[9px] uppercase tracking-widest text-zinc-500 dark:text-zinc-400">{{ $row['month'] }}</span>
                                </td>

                                @foreach ($agents as $a => $agent)
                                    @foreach ($metrics as $key => $meta)
                                        @php $val = $row['cells'][$a * $nb + $loop->index] ?? 0; @endphp
                                        <td class="border-b {{ $loop->first ? 'border-l' : '' }} {{ $border }} px-2 py-2 text-center tabular-nums group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700/60 {{ $val > 0 ? 'font-semibold '.$meta['color'] : 'text-zinc-300 dark:text-zinc-600' }}">
                                            {{ $val > 0 ? $val : '·' }}
                                        </td>
                                    @endforeach
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>

                        <tfoot class="sticky bottom-0 z-30">
                        <tr>
                            <td class="sticky left-0 z-40 {{ $head }} border-r border-t {{ $border }} px-4 py-2.5 text-center text-[10px] font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">
                                Total
                            </td>
                            @foreach ($agents as $a => $agent)
                                @foreach ($metrics as $key => $meta)
                                    @php $sum = $totals[$a * $nb + $loop->index] ?? 0; @endphp
                                    <td class="{{ $head }} border-t {{ $loop->first ? 'border-l' : '' }} {{ $border }} px-2 py-2.5 text-center tabular-nums {{ $sum > 0 ? 'text-sm font-bold '.$meta['color'] : 'text-zinc-300 dark:text-zinc-600' }}">
                                        {{ $sum > 0 ? $sum : '·' }}
                                    </td>
                                @endforeach
                            @endforeach
                        </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>
    </flux:card>
</div>
