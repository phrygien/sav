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
            <span class="text-amber-400">SAV</span>
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
                    </svg>
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
