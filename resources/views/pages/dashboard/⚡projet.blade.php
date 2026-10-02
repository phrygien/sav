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
    @if ($loaded)
        <div class="flex justify-end">
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
        </div>
    @endif

    @if (! $loaded)
        {{-- Squelettes pendant le chargement initial --}}
        <div class="grid auto-rows-min animate-pulse gap-4 md:grid-cols-3">
            @foreach (range(1, 6) as $i)
                <div wire:key="sk-project-{{ $i }}" class="flex flex-col gap-4 rounded-xl border border-zinc-200 bg-white p-6 dark:border-white/10 dark:bg-zinc-800">
                    <div class="flex items-start justify-between gap-2">
                        <div class="space-y-2">
                            <div class="h-5 w-32 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                            <div class="h-3 w-16 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                        </div>
                        <div class="h-5 w-12 rounded-full bg-zinc-200 dark:bg-zinc-700"></div>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        @foreach (range(1, 3) as $j)
                            <div wire:key="sk-project-{{ $i }}-stat-{{ $j }}" class="h-16 rounded-lg bg-zinc-100 dark:bg-zinc-700/50"></div>
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
        <div wire:loading.class="opacity-60" wire:target="refresh" class="grid auto-rows-min gap-4 transition-opacity md:grid-cols-3">
            @foreach ($projects as $project)
                <flux:card
                    wire:key="project-{{ $project['id'] }}"
                    :href="route('kanban', ['project' => $project['id']])"
                    wire:navigate
                    class="flex flex-col gap-4 transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <flux:heading size="lg">{{ $project['name'] ?? '' }}</flux:heading>
                            <flux:text class="text-xs">{{ $project['code'] ?? '' }}</flux:text>
                        </div>

                        <flux:badge size="sm" :color="($project['state'] ?? 0) === 1 ? 'green' : 'zinc'">
                            {{ ($project['state'] ?? 0) === 1 ? __('Actif') : __('Inactif') }}
                        </flux:badge>
                    </div>

                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-lg bg-amber-500/10 p-2">
                            <div class="text-xl font-semibold text-amber-600 dark:text-amber-400">
                                {{ number_format((int) ($project['pending_ticket'] ?? 0), 0, ',', ' ') }}
                            </div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('En attente') }}</div>
                        </div>

                        <div class="rounded-lg bg-blue-500/10 p-2">
                            <div class="text-xl font-semibold text-blue-600 dark:text-blue-400">
                                {{ number_format((int) ($project['in_progress_ticket'] ?? 0), 0, ',', ' ') }}
                            </div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('En cours') }}</div>
                        </div>

                        <div class="rounded-lg bg-green-500/10 p-2">
                            <div class="text-xl font-semibold text-green-600 dark:text-green-400">
                                {{ number_format((int) ($project['closed_ticket'] ?? 0), 0, ',', ' ') }}
                            </div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Clôturés') }}</div>
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif
</div>
