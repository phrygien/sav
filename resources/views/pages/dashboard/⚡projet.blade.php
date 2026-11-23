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
        // Mêmes couleurs que la répartition des tickets : attente = orange, en cours = bleu, clôturé = vert
        $stats = [
            'pending_ticket' => [
                'label'  => __('En attente'),
                'accent' => 'text-orange-600 dark:text-orange-400',
                'dot'    => 'bg-orange-400',
            ],
            'in_progress_ticket' => [
                'label'  => __('En cours'),
                'accent' => 'text-sky-600 dark:text-sky-400',
                'dot'    => 'bg-sky-400',
            ],
            'closed_ticket' => [
                'label'  => __('Clôturés'),
                'accent' => 'text-emerald-600 dark:text-emerald-400',
                'dot'    => 'bg-emerald-400',
            ],
        ];
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
                <div wire:key="sk-project-{{ $i }}" class="rounded-xl border border-zinc-300/70 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
                    <div class="flex items-start justify-between gap-2">
                        <div class="space-y-2">
                            <div class="h-4 w-28 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                            <div class="h-3 w-14 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                        </div>
                        <div class="h-5 w-12 rounded-full bg-zinc-200 dark:bg-zinc-700"></div>
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-3">
                        @foreach (range(1, 3) as $j)
                            <div wire:key="sk-project-{{ $i }}-stat-{{ $j }}" class="space-y-2">
                                <div class="h-5 w-8 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                                <div class="h-3 w-14 rounded bg-zinc-100 dark:bg-zinc-700/50"></div>
                            </div>
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
                <a
                    wire:key="project-{{ $project['id'] }}"
                    href="{{ route('kanban', ['project' => $project['id']]) }}"
                    wire:navigate
                    class="block rounded-xl border border-zinc-300/70 bg-white p-4 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:bg-zinc-700/40"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 space-y-0.5">
                            <div class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $project['name'] ?? '' }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $project['code'] ?? '' }}</div>
                        </div>

                        <flux:badge size="sm" :color="($project['state'] ?? 0) === 1 ? 'green' : 'zinc'">
                            {{ ($project['state'] ?? 0) === 1 ? __('Actif') : __('Inactif') }}
                        </flux:badge>
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-3">
                        @foreach ($stats as $field => $s)
                            @php $value = (int) ($project[$field] ?? 0); @endphp

                            <div>
                                {{-- Chiffre coloré selon le statut ; grisé quand il vaut 0 --}}
                                <div class="text-lg font-semibold tabular-nums {{ $value > 0 ? $s['accent'] : 'text-zinc-300 dark:text-zinc-600' }}">
                                    {{ number_format($value, 0, ',', ' ') }}
                                </div>

                                <div class="mt-0.5 flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                                    <span class="size-1.5 shrink-0 rounded-full {{ $value > 0 ? $s['dot'] : 'bg-zinc-200 dark:bg-zinc-700' }}"></span>
                                    <span class="truncate">{{ $s['label'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
