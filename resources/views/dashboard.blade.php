<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full mx-auto max-w-[100rem] flex-1 flex-col gap-8 px-4 pb-10 pt-4 lg:px-8 2xl:px-12">

        {{-- En-tête --}}
        <div class="space-y-1">
            <h1 class="text-2xl font-medium text-zinc-900 dark:text-white">{{ __('Dashboard') }}</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Vue d’ensemble de l’activité des tickets.') }}</p>
        </div>

        {{-- Projets --}}
        <section class="space-y-4">
            <livewire:pages::dashboard.projet />
        </section>

        <div class="border-t border-dashed border-zinc-300 dark:border-zinc-700"></div>

        {{-- Analyses : grille 2 × 2 --}}
        <section class="space-y-4">
            <div class="space-y-1">
                <h2 class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Analyses') }}</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Graphiques et détails de l’activité des tickets.') }}</p>
            </div>

            {{-- min-w-0 : empêche un graphique de dépasser sa colonne et de bloquer le redimensionnement --}}
            <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-2">
                <div class="min-w-0">
                    <livewire:pages::dashboard.tickets-chart />
                </div>

                <div class="min-w-0">
                    <livewire:pages::dashboard.partition-chart />
                </div>

                <div class="min-w-0">
                    <livewire:pages::dashboard.donut-chart />
                </div>

                <div class="min-w-0">
                    <livewire:pages::dashboard.user-activity-chart />
                </div>
            </div>
        </section>

        {{-- Détail par utilisateur : pleine largeur sous la grille --}}
        <section class="min-w-0">
            <livewire:pages::dashboard.user-activity-table />
        </section>
    </div>
</x-layouts::app>
