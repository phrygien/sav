<?php

use App\Services\CosmiaApi;
use Livewire\Component;

new class extends Component
{
    public array $users = [];

    /**
     * Projets par utilisateur : [user_id => [['id' => 3, 'name' => 'DIGIPARF'], ...]]
     * Une valeur null signifie que le chargement a échoué pour cet utilisateur.
     */
    public array $userProjects = [];

    public ?string $error = null;

    public function mount(CosmiaApi $api): void
    {
        try {
            $this->users = $api->get('user');
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->loadProjects($api);
    }

    private function loadProjects(CosmiaApi $api): void
    {
        $paths = [];

        foreach ($this->users as $user) {
            $paths[$user['id']] = 'user/userproject/'.$user['id'];
        }

        foreach ($api->getMany($paths) as $userId => $response) {
            $this->userProjects[$userId] = $response === null
                ? null
                : collect($response['result'] ?? [])
                    ->map(fn ($item) => [
                        'id'   => data_get($item, 'project_id'),
                        'name' => data_get($item, 'project_name')
                            ?? 'Projet #'.data_get($item, 'project_id'),
                    ])
                    ->unique('id')
                    ->values()
                    ->all();
        }
    }

    public function roleLabel(string $role): string
    {
        return match ($role) {
            'super_admin' => __('Super admin'),
            'simple_user' => __('Utilisateur'),
            default       => $role,
        };
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Utilisateurs') }}</flux:heading>
            <flux:subheading>
                {{ trans_choice(':count utilisateur|:count utilisateurs', count($users)) }}
            </flux:subheading>
        </div>

        @can('create-user')
            <flux:button
                variant="primary"
                icon="plus"
                :href="route('users.create')"
                wire:navigate
            >
                {{ __('Ajouter un utilisateur') }}
            </flux:button>
        @endcan
    </div>

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle" :heading="session('success')" />
    @endif

    @if ($error)
        <flux:callout variant="danger" icon="exclamation-circle" :heading="$error" />
    @elseif (empty($users))
        <flux:callout icon="users" :heading="__('Aucun utilisateur trouvé.')" />
    @else
        <flux:card class="overflow-hidden !p-0">
            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($users as $user)
                    @php $projects = $userProjects[$user['id']] ?? null; @endphp

                    <li
                        wire:key="user-{{ $user['id'] }}"
                        class="flex flex-col gap-3 px-5 py-4 transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50 sm:flex-row sm:items-center sm:gap-6"
                    >
                        {{-- Identité --}}
                        <div class="flex min-w-0 items-center gap-3 sm:w-72 sm:shrink-0">
                            <flux:avatar
                                :name="$user['name']"
                                color="auto"
                                :color:seed="$user['id']"
                                class="shrink-0"
                            />

                            <div class="min-w-0 flex-1">
                                <flux:heading class="truncate" title="{{ $user['name'] }}">
                                    {{ $user['name'] }}
                                </flux:heading>
                                <flux:text size="sm" class="truncate" title="{{ $user['email'] }}">
                                    {{ $user['email'] }}
                                </flux:text>
                            </div>

                            {{-- Bouton modifier (mobile : à côté de l'identité) --}}
                            @can('manage-access')
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil-square"
                                    :href="route('users.edit', $user['id'])"
                                    wire:navigate
                                    class="shrink-0 sm:hidden"
                                    :aria-label="__('Modifier :name', ['name' => $user['name']])"
                                />
                            @endcan
                        </div>

                        {{-- Rôle + statut --}}
                        <div class="flex flex-wrap items-center gap-1.5 sm:w-56 sm:shrink-0">
                            <flux:badge
                                size="sm"
                                inset="top bottom"
                                :color="$user['role'] === 'super_admin' ? 'purple' : 'zinc'"
                            >
                                {{ $this->roleLabel($user['role']) }}
                            </flux:badge>

                            @if ($user['treating'])
                                <flux:badge size="sm" inset="top bottom" color="green" icon="arrow-path">
                                    {{ __('En traitement') }}
                                </flux:badge>
                            @endif
                        </div>

                        {{-- Projets --}}
                        <div class="min-w-0 flex-1">
                            @if ($projects === null)
                                <flux:text size="sm" class="text-amber-600 dark:text-amber-400">
                                    {{ __('Projets indisponibles') }}
                                </flux:text>
                            @elseif (empty($projects))
                                <flux:text size="sm" class="italic">{{ __('Aucun projet') }}</flux:text>
                            @else
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($projects as $project)
                                        <flux:badge
                                            wire:key="user-{{ $user['id'] }}-project-{{ $project['id'] }}"
                                            size="sm"
                                            inset="top bottom"
                                            color="blue"
                                            icon="folder"
                                        >
                                            {{ $project['name'] }}
                                        </flux:badge>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Bouton modifier (desktop : à droite de la ligne) --}}
                        @can('manage-access')
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="pencil-square"
                                :href="route('users.edit', $user['id'])"
                                wire:navigate
                                class="hidden shrink-0 sm:inline-flex"
                                :aria-label="__('Modifier :name', ['name' => $user['name']])"
                            >
                                {{ __('Modifier') }}
                            </flux:button>
                        @endcan
                    </li>
                @endforeach
            </ul>
        </flux:card>
    @endif
</div>
