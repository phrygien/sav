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

    <flux:brand href="#" logo="https://fluxui.dev/img/demo/logo.png" name="Acme Inc." class="max-lg:hidden dark:hidden" />
    <flux:brand href="#" logo="https://fluxui.dev/img/demo/dark-mode-logo.png" name="Acme Inc." class="max-lg:hidden! hidden dark:flex" />

    <flux:navbar class="max-lg:hidden">
        <flux:navbar.item href="{{ route('kanban') }}" wire:navigate>Toutes les demandes</flux:navbar.item>
        <flux:navbar.item href="{{ route('kanban.retour.retractation') }}" wire:navigate>Retour & Retractations</flux:navbar.item>
        <flux:navbar.item href="{{ route('kanban.changement.adresse') }}" wire:navigate>Changement d'adresse</flux:navbar.item>
        <flux:navbar.item href="{{ route('kanban.invertion.colis') }}" wire:navigate>Invertion de colis</flux:navbar.item>
        <flux:navbar.item href="{{ route('tiket.redondant')  }}" wire:navigate>Tickets redondants</flux:navbar.item>
    </flux:navbar>

    <flux:spacer />

    @auth
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
        <flux:sidebar.brand
            href="#"
            logo="https://fluxui.dev/img/demo/logo.png"
            logo:dark="https://fluxui.dev/img/demo/dark-mode-logo.png"
            name="Acme Inc."
        />

        <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
    </flux:sidebar.header>

    <flux:sidebar.nav>
        <flux:sidebar.item icon="home" href="{{ route('kanban.retour.retractation') }}" wire:navigate current>Retour & Retractations</flux:sidebar.item>
        <flux:sidebar.item icon="inbox" badge="12" href="#">Changement d'adresse</flux:sidebar.item>
        <flux:sidebar.item icon="document-text" href="#">Invertion colis</flux:sidebar.item>
        <flux:sidebar.item icon="calendar" href="#">Ticket redodant</flux:sidebar.item>
    </flux:sidebar.nav>

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
@fluxScripts
</body>
</html>
