<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
<div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
    <div class="bg-muted relative hidden h-full flex-col p-10 text-white lg:flex dark:border-e dark:border-neutral-800">

        {{-- Image Unsplash : bureau avec tâches terminées (Photo par Jakub Żerdzicki) --}}
        <div class="absolute inset-0 bg-cover bg-center bg-no-repeat"
             style="background-image: url('https://unsplash.com/photos/E9abH9GT-io/download?force=true&w=1600');"></div>

        {{-- Overlay sombre pour garder le texte lisible --}}
        <div class="absolute inset-0 bg-linear-to-t from-neutral-950/90 via-neutral-950/50 to-neutral-950/30"></div>

        {{-- Logo texte « SAV CosmIA » : le I est remplacé par une ampoule --}}
        <a href="{{ route('home') }}" wire:navigate aria-label="SAV CosmIA" class="relative z-20 inline-flex items-center self-start text-2xl font-extrabold tracking-tight text-white">
            <span class="text-amber-400">COSM</span>
            <span class="ml-2 inline-flex items-center">
                IA
            </span>
        </a>

        <div class="relative z-20 mt-auto space-y-4">

            {{-- Date et heure en direct --}}
            <div x-data="{
                    now: new Date(),
                    timer: null,
                    locale: '{{ str_replace('_', '-', app()->getLocale()) }}',
                    init() { this.timer = setInterval(() => this.now = new Date(), 1000) },
                    destroy() { clearInterval(this.timer) }
                 }"
                 class="space-y-1">
                <p class="text-base capitalize text-white"
                   x-text="now.toLocaleDateString(locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })"></p>
                <p class="text-6xl font-bold tabular-nums tracking-tight text-white"
                   x-text="now.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit', second: '2-digit' })"></p>
            </div>

            {{-- Phrase unique SAV & tickets --}}
            <h2 class="max-w-xl text-4xl font-bold leading-tight text-white">
                Suivez et résolvez chaque ticket SAV, simplement.
            </h2>
        </div>
    </div>

    <div class="w-full lg:p-8">
        <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
            {{-- Logo mobile (le panneau de gauche est masqué sous lg) --}}
            <a href="{{ route('home') }}" wire:navigate aria-label="SAV CosmIA" class="z-20 flex items-center justify-center text-3xl font-extrabold tracking-tight text-zinc-900 dark:text-white lg:hidden">
                <span class="text-amber-500">COSM</span>
                <span class="ml-2 inline-flex items-center">
                    IA
                </span>
            </a>

            {{ $slot }}
        </div>
    </div>
</div>

@persist('toast')
<flux:toast.group>
    <flux:toast />
</flux:toast.group>
@endpersist

@fluxScripts
</body>
</html>
