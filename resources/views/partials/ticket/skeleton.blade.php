{{-- Skeleton du détail ticket : reprend la structure de tabs-nav + tab-description --}}
<flux:skeleton.group animate="shimmer" class="flex flex-col gap-6 sm:flex-row sm:items-start">

    {{-- ───────────── Navigation des onglets ───────────── --}}
    {{-- Mobile : select --}}
    <flux:skeleton class="h-10 w-full rounded-lg sm:hidden" />

    {{-- Desktop : liste verticale (ajuster sm:w-56 à la largeur réelle de tabs-nav) --}}
    <div class="hidden w-56 shrink-0 space-y-2 sm:block">
        @foreach (range(1, 4) as $i)
            <div class="flex items-center gap-3 rounded-lg px-3 py-2.5">
                <flux:skeleton class="size-5 shrink-0 rounded" />
                <flux:skeleton.line class="w-full" />
            </div>
        @endforeach
    </div>

    {{-- ───────────── Contenu ───────────── --}}
    <div class="min-w-0 flex-1 space-y-6">

        {{-- En-tête : titre, n° ticket + statut, actions --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 flex-1 space-y-3">
                <flux:skeleton class="h-8 w-3/4 rounded-md" />

                <div class="flex flex-wrap items-center gap-2">
                    <flux:skeleton class="h-4 w-16 rounded" />
                    <flux:skeleton class="h-5 w-20 rounded-md" />
                </div>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <flux:skeleton class="h-10 w-32 rounded-lg" />
                <flux:skeleton class="h-10 w-40 rounded-lg" />
            </div>
        </div>

        {{-- Grille : principal (2/3) + latéral (1/3) --}}
        <div class="grid gap-6 lg:grid-cols-3">

            {{-- ===== Colonne principale ===== --}}
            <div class="space-y-6 lg:col-span-2">

                {{-- Résumé --}}
                <div class="rounded-lg border border-zinc-200 dark:border-white/10">
                    <div class="space-y-2 border-b border-zinc-200 px-4 py-4 sm:px-6 dark:border-white/10">
                        <flux:skeleton class="h-5 w-24 rounded" />
                        <flux:skeleton.line class="w-56" />
                    </div>

                    <div class="space-y-3 p-4 sm:p-6">
                        @foreach (['w-full', 'w-11/12', 'w-4/5', 'w-2/3'] as $width)
                            <div class="flex items-start gap-2">
                                <flux:skeleton class="mt-0.5 size-4 shrink-0 rounded-full" />
                                <flux:skeleton.line class="{{ $width }}" />
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Note --}}
                <div class="rounded-lg border border-zinc-200 dark:border-white/10">
                    <div class="space-y-2 border-b border-zinc-200 px-4 py-4 sm:px-6 dark:border-white/10">
                        <flux:skeleton class="h-5 w-20 rounded" />
                        <flux:skeleton.line class="w-64" />
                    </div>

                    <div class="p-4 sm:p-6">
                        {{-- Éditeur : barre d'outils + zone de saisie --}}
                        <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-white/10">
                            <div class="flex flex-wrap gap-2 border-b border-zinc-200 p-2 dark:border-white/10">
                                @foreach (range(1, 8) as $i)
                                    <flux:skeleton class="size-7 rounded" />
                                @endforeach
                            </div>
                            <flux:skeleton class="h-[260px] w-full rounded-none" />
                        </div>

                        <div class="mt-4 flex justify-end">
                            <flux:skeleton class="h-10 w-44 rounded-lg" />
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== Colonne latérale (en premier sur mobile, comme la vraie page) ===== --}}
            <aside class="order-first lg:order-none lg:col-span-1">
                <div class="rounded-lg border border-zinc-200 dark:border-white/10">
                    <div class="space-y-2 border-b border-zinc-200 px-4 py-4 dark:border-white/10">
                        <flux:skeleton class="h-5 w-28 rounded" />
                        <flux:skeleton.line class="w-48" />
                    </div>

                    <div class="divide-y divide-zinc-200 dark:divide-white/10">
                        {{-- Groupe « Ticket » : 2 champs / Groupe « Client » : 3 champs --}}
                        @foreach ([2, 3] as $count)
                            <section class="p-4">
                                <flux:skeleton class="mb-4 h-3 w-16 rounded" />

                                <div class="space-y-4">
                                    @foreach (range(1, $count) as $i)
                                        <div class="space-y-2">
                                            <flux:skeleton class="h-3 w-24 rounded" />
                                            <flux:skeleton class="h-4 w-3/4 rounded" />
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    </div>
                </div>
            </aside>
        </div>
    </div>
</flux:skeleton.group>
