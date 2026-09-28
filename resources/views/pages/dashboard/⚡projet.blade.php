<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

new class extends Component
{
    public array $projects = [];

    public ?string $error = null;

    public function mount(): void
    {
        $this->loadProjects();
    }

    public function loadProjects(): void
    {
        $this->error = null;

        try {
            $request = Http::baseUrl(config('services.cosmia.url'))
                ->withHeaders(['x-secret-key' => config('services.cosmia.secret')])
                ->acceptJson()
                ->timeout(10);

            // Token de session issu du login (à retirer si l'endpoint n'en a pas besoin)
            if ($token = session('cosmia_token')) {
                $request = $request->withToken($token);
            }

            $response = $request->get('/project');
        } catch (ConnectionException) {
            $this->projects = [];
            $this->error = __('Service indisponible, réessaie dans un instant.');

            return;
        }

        if ($response->failed()) {
            $this->projects = [];
            $this->error = $response->json('error')
                ?? $response->json('message')
                ?? __('Impossible de charger les projets.');

            return;
        }

        $this->projects = $response->json() ?? [];
    }
};
?>

<div class="flex flex-col gap-4">
    @if ($error)
        <flux:callout variant="danger" icon="exclamation-circle" :heading="$error">
            <x-slot name="actions">
                <flux:button size="sm" wire:click="loadProjects">{{ __('Réessayer') }}</flux:button>
            </x-slot>
        </flux:callout>
    @elseif (empty($projects))
        <flux:text>{{ __('Aucun projet.') }}</flux:text>
    @else
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            @foreach ($projects as $project)
                <flux:card
                    wire:key="project-{{ $project['id'] }}"
                    :href="route('kanban', ['project' => $project['id']])"
                    wire:navigate
                    class="flex flex-col gap-4 transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <flux:heading size="lg">{{ $project['name'] }}</flux:heading>
                            <flux:text class="text-xs">{{ $project['code'] }}</flux:text>
                        </div>

                        <flux:badge size="sm" :color="$project['state'] === 1 ? 'green' : 'zinc'">
                            {{ $project['state'] === 1 ? __('Actif') : __('Inactif') }}
                        </flux:badge>
                    </div>

                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-lg bg-amber-500/10 p-2">
                            <div class="text-xl font-semibold text-amber-600 dark:text-amber-400">
                                {{ number_format($project['pending_ticket'], 0, ',', ' ') }}
                            </div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('En attente') }}</div>
                        </div>

                        <div class="rounded-lg bg-blue-500/10 p-2">
                            <div class="text-xl font-semibold text-blue-600 dark:text-blue-400">
                                {{ number_format($project['in_progress_ticket'], 0, ',', ' ') }}
                            </div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('En cours') }}</div>
                        </div>

                        <div class="rounded-lg bg-green-500/10 p-2">
                            <div class="text-xl font-semibold text-green-600 dark:text-green-400">
                                {{ number_format($project['closed_ticket'], 0, ',', ' ') }}
                            </div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Clôturés') }}</div>
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif
</div>
