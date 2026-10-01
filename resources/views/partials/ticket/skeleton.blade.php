{{-- Squelette pendant le chargement initial du ticket --}}
<div class="flex animate-pulse flex-col gap-6 sm:flex-row sm:items-start">
    {{-- Navigation des onglets (mobile) --}}
    <div class="h-10 w-full rounded-md bg-zinc-200 sm:hidden dark:bg-zinc-700"></div>

    {{-- Navigation des onglets (desktop) --}}
    <div class="hidden space-y-2 sm:block sm:w-52 sm:shrink-0 lg:w-60">
        @foreach (range(1, 4) as $i)
            <div wire:key="sk-tab-{{ $i }}" class="h-10 rounded-lg bg-zinc-200 dark:bg-zinc-700"></div>
        @endforeach
    </div>

    <div class="min-w-0 flex-1 space-y-6">
        {{-- Barre d'actions : badges à gauche, boutons à droite --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <div class="h-6 w-24 rounded-full bg-zinc-200 dark:bg-zinc-700"></div>
                <div class="h-6 w-20 rounded-full bg-zinc-200 dark:bg-zinc-700"></div>
            </div>

            <div class="flex items-center gap-2">
                <div class="h-8 w-28 rounded-md bg-zinc-200 dark:bg-zinc-700"></div>
                <div class="h-8 w-36 rounded-md bg-zinc-200 dark:bg-zinc-700"></div>
            </div>
        </div>

        {{-- Infos ticket : pleine largeur, 3 colonnes --}}
        <div class="w-full rounded-lg border border-zinc-200 dark:border-white/10">
            <div class="space-y-2 border-b border-zinc-200 px-4 py-4 dark:border-white/10">
                <div class="h-4 w-32 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                <div class="h-3 w-56 rounded bg-zinc-200 dark:bg-zinc-700"></div>
            </div>

            <div class="grid grid-cols-1 gap-x-6 gap-y-5 p-4 sm:grid-cols-3">
                @foreach (range(1, 7) as $i)
                    <div wire:key="sk-field-{{ $i }}" class="space-y-2">
                        <div class="h-3 w-20 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                        <div class="h-4 w-3/4 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Bas : Résumé + Note --}}
        <div class="grid gap-6 md:grid-cols-2">
            {{-- Résumé --}}
            <div class="space-y-3 rounded-lg bg-zinc-50 p-4 sm:p-6 dark:bg-zinc-900">
                <div class="h-4 w-24 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                <div class="h-3 w-full rounded bg-zinc-200 dark:bg-zinc-700"></div>
                <div class="h-3 w-5/6 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                <div class="h-3 w-2/3 rounded bg-zinc-200 dark:bg-zinc-700"></div>
            </div>

            {{-- Note (éditeur) : titre, barre d'outils, zone de saisie, bouton --}}
            <div class="space-y-3">
                <div class="h-4 w-16 rounded bg-zinc-200 dark:bg-zinc-700"></div>

                <div class="overflow-hidden rounded-md border border-zinc-200 dark:border-white/10">
                    <div class="flex items-center gap-2 border-b border-zinc-200 p-2 dark:border-white/10">
                        @foreach (range(1, 6) as $i)
                            <div wire:key="sk-tool-{{ $i }}" class="h-6 w-6 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                        @endforeach
                    </div>
                    <div class="h-[250px] bg-zinc-100/60 dark:bg-zinc-800/60"></div>
                </div>

                <div class="flex justify-end">
                    <div class="h-8 w-36 rounded-md bg-zinc-200 dark:bg-zinc-700"></div>
                </div>
            </div>
        </div>
    </div>
</div>
