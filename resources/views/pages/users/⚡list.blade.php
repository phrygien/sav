<?php

use App\Services\CosmiaApi;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    // Cache court (secondes) : la liste des utilisateurs et leurs projets bougent peu
    private const CACHE_TTL = 120;

    /**
     * Lignes déjà mises en forme, prêtes à afficher :
     * ['id', 'name', 'email', 'is_admin', 'role_label', 'treating', 'projects']
     * 'projects' = null si le chargement a échoué pour cet utilisateur,
     * sinon une liste de ['id' => 3, 'name' => 'DIGIPARF'].
     */
    #[Locked]
    public array $rows = [];

    public ?string $error = null;

    /** false tant que les données n'ont pas été chargées (rendu initial = squelette) */
    public bool $loaded = false;

    /**
     * Cache chaud : liste complète dès la première réponse, sans squelette ni 2e requête.
     * Cache vide : squelette, puis load() via wire:init.
     */
    public function mount(): void
    {
        $cached = Cache::get($this->cacheKey());

        if (is_array($cached)) {
            $this->rows   = $cached;
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

    /** « Réessayer » / « Actualiser » : ignore le cache */
    public function refresh(): void
    {
        $this->loadData(true);
        $this->loaded = true;
    }

    // Clé liée à la session : les droits de l'API dépendent du token de l'utilisateur
    // (clé distincte de « cosmia.users » du Kanban, qui stocke un format différent)
    private function cacheKey(): string
    {
        return 'cosmia.users.list.'.sha1((string) session('cosmia_token'));
    }

    private function loadData(bool $force = false): void
    {
        $this->error = null;

        $key = $this->cacheKey();

        if (! $force && is_array($cached = Cache::get($key))) {
            $this->rows = $cached;

            return;
        }

        $api = app(CosmiaApi::class);

        try {
            $users = $api->get('user');
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();
            $this->rows  = [];

            return;
        }

        $users = array_is_list($users) ? $users : ($users['data'] ?? []);

        // Un appel par utilisateur, tous lancés en parallèle (un seul tour réseau)
        $paths = [];

        foreach ($users as $user) {
            if (isset($user['id'])) {
                $paths[$user['id']] = 'user/userproject/'.$user['id'];
            }
        }

        $responses = $api->getMany($paths);

        $this->rows = collect($users)->map(function ($user) use ($responses) {
            $response = $responses[$user['id'] ?? null] ?? null;
            $role     = (string) ($user['role'] ?? '');

            return [
                'id'         => $user['id'] ?? null,
                'name'       => (string) ($user['name'] ?? ''),
                'email'      => (string) ($user['email'] ?? ''),
                'is_admin'   => $role === 'super_admin',
                'role_label' => $this->roleLabel($role),
                'treating'   => (bool) ($user['treating'] ?? false),
                'projects'   => $response === null
                    ? null
                    : collect($response['result'] ?? [])
                        ->map(fn ($item) => [
                            'id'   => data_get($item, 'project_id'),
                            'name' => data_get($item, 'project_name')
                                ?? 'Projet #'.data_get($item, 'project_id'),
                        ])
                        ->unique('id')
                        ->values()
                        ->all(),
            ];
        })->all();

        // Les erreurs ne sont jamais mises en cache : si un seul appel a échoué, on ne garde rien
        $complete = ! collect($this->rows)->contains(fn ($r) => $r['projects'] === null);

        if ($complete) {
            Cache::put($key, $this->rows, self::CACHE_TTL);
        }
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'super_admin' => __('Super admin'),
            'simple_user' => __('Utilisateur'),
            default       => $role,
        };
    }
};
?>

{{-- wire:init sur un <div> : Blade n'accepte pas @if dans les attributs d'un composant <flux:...> --}}
<div class="space-y-6" @if (! $loaded) wire:init="load" @endif>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Utilisateurs') }}</flux:heading>
            <flux:subheading>
                @if ($loaded)
                    {{ trans_choice(':count utilisateur|:count utilisateurs', count($rows)) }}
                @else
                    <span class="inline-block h-4 w-24 animate-pulse rounded bg-zinc-200 align-middle dark:bg-zinc-700"></span>
                @endif
            </flux:subheading>
        </div>

        <div class="flex items-center gap-2">
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
    </div>

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle" :heading="session('success')" />
    @endif

    @if (! $loaded)
        {{-- Squelette pendant le chargement initial --}}
        <flux:card class="overflow-hidden !p-0">
            <ul class="animate-pulse divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach (range(1, 6) as $i)
                    <li wire:key="sk-user-{{ $i }}" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:gap-6">
                        <div class="flex items-center gap-3 sm:w-72 sm:shrink-0">
                            <div class="size-10 shrink-0 rounded-full bg-zinc-200 dark:bg-zinc-700"></div>
                            <div class="flex-1 space-y-2">
                                <div class="h-4 w-32 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                                <div class="h-3 w-44 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                            </div>
                        </div>

                        <div class="flex gap-1.5 sm:w-56 sm:shrink-0">
                            <div class="h-5 w-20 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                            <div class="h-5 w-24 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                        </div>

                        <div class="flex flex-1 gap-1.5">
                            <div class="h-5 w-24 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                            <div class="h-5 w-20 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </flux:card>
    @elseif ($error)
        <flux:callout variant="danger" icon="exclamation-circle" :heading="$error">
            <x-slot name="actions">
                <flux:button size="sm" wire:click="refresh">{{ __('Réessayer') }}</flux:button>
            </x-slot>
        </flux:callout>
    @elseif (empty($rows))
        <flux:callout icon="users" :heading="__('Aucun utilisateur trouvé.')" />
    @else
        <flux:card wire:loading.class="opacity-60" wire:target="refresh" class="overflow-hidden !p-0 transition-opacity">
            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($rows as $user)
                    @php $projects = $user['projects']; @endphp

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
                                :color="$user['is_admin'] ? 'purple' : 'zinc'"
                            >
                                {{ $user['role_label'] }}
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
