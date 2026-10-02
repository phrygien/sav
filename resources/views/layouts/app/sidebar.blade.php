<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800 antialiased">
@php
    $user = auth()->user();
    $initials = collect(explode(' ', trim($user?->name ?? '')))
        ->filter()
        ->take(2)
        ->map(fn ($word) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($word, 0, 1)))
        ->implode('');
@endphp

<flux:header class="sticky top-0 z-20 bg-zinc-50 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700">
    <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

    {{-- Logo texte « SAV CosmIA » : le I est remplacé par une ampoule --}}
    <a href="{{ route('kanban') }}" wire:navigate aria-label="SAV CosmIA" class="max-lg:hidden mr-4 inline-flex items-center text-xl font-extrabold tracking-tight text-zinc-900 dark:text-white">
        <span class="text-amber-500">Cosm</span>
        <span class="ml-2 inline-flex items-center">
            IA
        </span>
    </a>
    <flux:separator vertical class="my-2 mx-2" />

    <flux:navbar class="max-lg:hidden">
        <flux:navbar.item href="{{ route('kanban') }}" wire:navigate>Toutes les demandes</flux:navbar.item>
        <flux:navbar.item href="{{ route('kanban.retour.retractation') }}" wire:navigate>Retour & Retractations</flux:navbar.item>
        <flux:navbar.item href="{{ route('kanban.changement.adresse') }}" wire:navigate>Changement d'adresse</flux:navbar.item>
        <flux:navbar.item href="{{ route('kanban.invertion.colis') }}" wire:navigate>Invertion de colis</flux:navbar.item>
        <flux:navbar.item href="{{ route('tiket.redondant') }}" wire:navigate>Tickets redondants</flux:navbar.item>
    </flux:navbar>

    <flux:spacer />

    @auth
        @can('manage-access')
            <flux:dropdown>
                <flux:navbar.item icon:trailing="chevron-down">Accès & Sécurité</flux:navbar.item>

                <flux:navmenu>
                    <flux:navmenu.item href="{{ route('users.list') }}" wire:navigate icon="users">
                        Utilisateurs
                    </flux:navmenu.item>

                    @can('create-user')
                        <flux:navmenu.item href="{{ route('users.create') }}" wire:navigate icon="user-plus">
                            Ajouter un utilisateur
                        </flux:navmenu.item>
                    @endcan

                    @can('assign-user-project')
                        <flux:navmenu.item href="#" icon="link">
                            Associer un utilisateur à un projet
                        </flux:navmenu.item>
                    @endcan
                </flux:navmenu>
            </flux:dropdown>

            <flux:separator vertical class="my-2 mx-2" />
        @endcan

        <flux:dropdown position="bottom" align="end">
            <flux:profile
                :initials="$initials"
                :name="$user->name"
                icon-trailing="chevron-down"
                class="max-lg:[&_[data-flux-profile-name]]:hidden"
            />

            <flux:menu class="min-w-56">
                <div class="flex items-center gap-3 px-2 py-2">
                    <flux:avatar :name="$user->name" :initials="$initials" size="sm" />
                    <div class="grid text-start leading-tight">
                        <span class="truncate text-sm font-semibold">{{ $user->name }}</span>
                        <span class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $user->email }}</span>
                    </div>
                </div>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                        {{ __('Log out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    @endauth
</flux:header>

<flux:sidebar sticky collapsible="mobile" class="lg:hidden bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700">
    <flux:sidebar.header>
        {{-- Même logo que l'en-tête --}}
        <a href="{{ route('kanban') }}" wire:navigate aria-label="SAV CosmIA" class="inline-flex items-center text-xl font-extrabold tracking-tight text-zinc-900 dark:text-white">
            <span class="text-amber-500">SAV</span>
            <span class="ml-2 inline-flex items-center">
                Cosm<svg viewBox="0 0 24 34" xmlns="http://www.w3.org/2000/svg" class="mx-px h-[1.25em] w-auto" aria-hidden="true">
                    <circle cx="12" cy="12" r="11" fill="#fbbf24" opacity=".18">
                        <animate attributeName="opacity" values=".12;.3;.12" dur="2.4s" repeatCount="indefinite"/>
                    </circle>
                    <path d="M12 2.5 a8.5 8.5 0 0 1 4.8 15.5 c-.9.7-1.3 1.6-1.3 2.7 v.8 h-7 v-.8 c0-1.1-.4-2-1.3-2.7 A8.5 8.5 0 0 1 12 2.5z" fill="#fbbf24"/>
                    <path d="M8 8.5 a5 5 0 0 1 3-3" stroke="#fff" stroke-width="1.2" stroke-linecap="round" fill="none" opacity=".7"/>
                    <path d="M9.8 20 v-4.5 l2.2 2.2 l2.2 -2.2 V20" stroke="#92400e" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round" fill="none">
                        <animate attributeName="opacity" values="1;.65;1;.85;1" dur="2.4s" repeatCount="indefinite"/>
                    </path>
                    <rect x="8" y="21.8" width="8" height="2.6" rx="1" fill="currentColor" opacity=".85"/>
                    <rect x="8.4" y="25" width="7.2" height="2.6" rx="1" fill="currentColor" opacity=".7"/>
                    <path d="M9.6 28.2 h4.8 a2.4 2.4 0 0 1 -4.8 0z" fill="currentColor" opacity=".85"/>
                </svg>A
            </span>
        </a>

        <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
    </flux:sidebar.header>

    <flux:sidebar.nav>
        <flux:sidebar.item icon="home" href="{{ route('kanban') }}" wire:navigate>Toutes les demandes</flux:sidebar.item>
        <flux:sidebar.item icon="arrow-uturn-left" href="{{ route('kanban.retour.retractation') }}" wire:navigate>Retour & Retractations</flux:sidebar.item>
        <flux:sidebar.item icon="inbox" href="{{ route('kanban.changement.adresse') }}" wire:navigate>Changement d'adresse</flux:sidebar.item>
        <flux:sidebar.item icon="document-text" href="{{ route('kanban.invertion.colis') }}" wire:navigate>Invertion colis</flux:sidebar.item>
        <flux:sidebar.item icon="calendar" href="{{ route('tiket.redondant') }}" wire:navigate>Tickets redondants</flux:sidebar.item>
    </flux:sidebar.nav>

    @can('manage-access')
        <flux:sidebar.group expandable heading="Accès & Sécurité" class="grid">
            <flux:sidebar.item icon="users" href="{{ route('users.list') }}" wire:navigate>Utilisateurs</flux:sidebar.item>

            @can('create-user')
                <flux:sidebar.item icon="user-plus" href="{{ route('users.create') }}" wire:navigate>
                    Ajouter un utilisateur
                </flux:sidebar.item>
            @endcan

        </flux:sidebar.group>
    @endcan

    <flux:sidebar.spacer />

    <flux:sidebar.nav>
        <flux:sidebar.item icon="cog-6-tooth" href="#">Settings</flux:sidebar.item>
    </flux:sidebar.nav>

    @auth
        <flux:separator />

        <div class="flex items-center gap-3 px-2 py-2">
            <flux:avatar :name="$user->name" :initials="$initials" size="sm" />
            <div class="grid text-start leading-tight">
                <span class="truncate text-sm font-semibold">{{ $user->name }}</span>
                <span class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $user->email }}</span>
            </div>
        </div>
    @endauth
</flux:sidebar>

{{ $slot }}
@persist('toast')
<flux:toast position="top end" />
@endpersist
@fluxScripts
</body>
</html>
