<?php

use App\Services\CosmiaApi;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

new class extends Component
{
    // Les compteurs de tickets bougent souvent : cache court (secondes)
    private const CACHE_TTL = 60;

    private const CACHE_KEY = 'cosmia.projects.dashboard';

    public array $projects = [];

    public ?string $error = null;

    /** false tant que les projets n'ont pas été chargés (rendu initial = squelettes) */
    public bool $loaded = false;

    /**
     * Cache chaud : rendu complet immédiat, sans squelette ni deuxième requête.
     * Cache vide : squelettes, puis loadProjects() via wire:init.
     */
    public function mount(): void
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            $this->projects = $cached;
            $this->loaded   = true;
        }
    }

    /** Chargement différé (wire:init), « Réessayer » et « Actualiser » */
    public function loadProjects(bool $force = false): void
    {
        $this->error = null;

        if (! $force) {
            $cached = Cache::get(self::CACHE_KEY);

            if (is_array($cached)) {
                $this->projects = $cached;
                $this->loaded   = true;

                return;
            }
        }

        try {
            $response = app(CosmiaApi::class)->get('/project');
        } catch (\RuntimeException $e) {
            $this->error  = $e->getMessage();
            $this->loaded = true;

            return;
        }

        // Réponse tolérée sous forme de liste ou enveloppée dans « data »
        $list = $response['data'] ?? $response;
        $list = array_is_list($list) ? $list : [];

        // Les erreurs ne sont jamais mises en cache
        Cache::put(self::CACHE_KEY, $list, self::CACHE_TTL);

        $this->projects = $list;
        $this->loaded   = true;
    }

    public function refresh(): void
    {
        $this->loadProjects(true);
    }
};
?>

<div class="flex flex-col gap-4" @if (! $loaded) wire:init="loadProjects" @endif>
    @php
        // Pastilles : attente = orange, en cours = bleu, clôturé = vert (grisées quand la valeur est 0)
        $stats = [
            'pending_ticket' => [
                'label' => __('En attente'),
                'icon'  => 'clock',
                'tag'   => 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-500/30 dark:bg-orange-500/10 dark:text-orange-300',
            ],
            'in_progress_ticket' => [
                'label' => __('En cours'),
                'icon'  => 'arrow-path',
                'tag'   => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-300',
            ],
            'closed_ticket' => [
                'label' => __('Clôturés'),
                'icon'  => 'check-circle',
                'tag'   => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300',
            ],
        ];

        $tagBase  = 'inline-flex items-center gap-1.5 rounded-sm border px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide';
        $tagEmpty = 'border-zinc-200 bg-zinc-50 text-zinc-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-500';
    @endphp

    {{-- En-tête de section : titre + description, actualiser à droite --}}
    <div class="flex items-start justify-between gap-4">
        <div class="space-y-1">
            <h2 class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Projets') }}</h2>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Tickets en attente, en cours et clôturés par projet.') }}</p>
        </div>

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

    @if (! $loaded)
        {{-- Squelettes pendant le chargement initial --}}
        <div class="grid animate-pulse gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5">
            @foreach (range(1, 5) as $i)
                <div wire:key="sk-project-{{ $i }}" class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800">
                    <div class="h-3 w-24 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                    <div class="mt-3 h-8 w-16 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                    <div class="mt-4 flex gap-2">
                        @foreach (range(1, 3) as $j)
                            <div wire:key="sk-project-{{ $i }}-stat-{{ $j }}" class="h-5 w-16 rounded-sm bg-zinc-100 dark:bg-zinc-700/50"></div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @elseif ($error)
        <flux:callout variant="danger" icon="exclamation-circle" :heading="$error">
            <x-slot name="actions">
                <flux:button size="sm" wire:click="refresh">{{ __('Réessayer') }}</flux:button>
            </x-slot>
        </flux:callout>
    @elseif (empty($projects))
        <flux:text>{{ __('Aucun projet.') }}</flux:text>
    @else
        <div
            wire:loading.class="opacity-60"
            wire:target="refresh"
            class="grid gap-4 transition-opacity md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5"
        >
            @foreach ($projects as $project)
                @php
                    $total  = 0;
                    foreach ($stats as $field => $s) {
                        $total += (int) ($project[$field] ?? 0);
                    }
                    $active = ($project['state'] ?? 0) === 1;
                @endphp

                <a
                    wire:key="project-{{ $project['id'] }}"
                    href="{{ route('kanban', ['project' => $project['id']]) }}"
                    wire:navigate
                    class="block rounded-lg border border-zinc-200 bg-white p-5 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:bg-zinc-700/40"
                >
                    {{-- Libellé : nom du projet en petites capitales + état --}}
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="truncate text-[11px] font-medium uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                {{ $project['name'] ?? '' }}
                            </div>

                            @if (! empty($project['code']))
                                <div class="mt-0.5 text-[10px] uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ $project['code'] }}</div>
                            @endif
                        </div>

                        <span @class([
                            'size-2 shrink-0 rounded-full mt-1',
                            'bg-emerald-500' => $active,
                            'bg-zinc-300 dark:bg-zinc-600' => ! $active,
                        ]) title="{{ $active ? __('Actif') : __('Inactif') }}"></span>
                    </div>

                    {{-- Valeur principale : total des tickets du projet --}}
                    <div class="mt-2 text-3xl font-bold tabular-nums text-zinc-900 dark:text-white">
                        {{ number_format($total, 0, ',', ' ') }}
                    </div>

                    {{-- Pastilles par statut --}}
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($stats as $field => $s)
                            @php $value = (int) ($project[$field] ?? 0); @endphp

                            <span class="{{ $tagBase }} {{ $value > 0 ? $s['tag'] : $tagEmpty }}">
                                <span class="tabular-nums">{{ number_format($value, 0, ',', ' ') }}</span>
                                <span>{{ $s['label'] }}</span>
                                <flux:icon :name="$s['icon']" variant="micro" class="size-3.5" />
                            </span>
                        @endforeach
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
