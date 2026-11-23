<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full mx-auto max-w-[90rem] flex-1 flex-col gap-8 px-4 pb-10 pt-4 lg:px-8">

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

        {{-- Analyses : bloc à onglets --}}
        <section
            class="space-y-4"
            x-data="{
                tab: 'tickets',
                tabs: {
                    tickets:     { label: @js(__('Tickets')),      hint: @js(__('Évolution des tickets sur la période.')) },
                    partition:   { label: @js(__('Partition')),    hint: @js(__('Répartition des tickets.')) },
                    repartition: { label: @js(__('Répartition')),  hint: @js(__('Part de chaque catégorie.')) },
                    activite:    { label: @js(__('Activité')),     hint: @js(__('Activité des utilisateurs.')) },
                    utilisateurs:{ label: @js(__('Utilisateurs')), hint: @js(__('Détail de l’activité par utilisateur.')) },
                },
                select(key) {
                    this.tab = key;
                    // Force les graphiques à se redimensionner une fois l'onglet visible
                    this.$nextTick(() => window.dispatchEvent(new Event('resize')));
                },
            }"
        >
            <div class="space-y-1">
                <h2 class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Analyses') }}</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Graphiques et détails de l’activité des tickets.') }}</p>
            </div>

            <div class="overflow-hidden rounded-xl border border-zinc-300/70 bg-white dark:border-zinc-700 dark:bg-zinc-800">

                {{-- Onglets --}}
                <div class="grid grid-cols-2 divide-x divide-zinc-200 border-b border-zinc-200 md:grid-cols-5 dark:divide-zinc-700 dark:border-zinc-700">
                    <template x-for="(item, key) in tabs" :key="key">
                        <button
                            type="button"
                            @click="select(key)"
                            class="-mb-px border-b-2 px-5 py-3 text-left transition"
                            :class="tab === key
                                ? 'border-zinc-900 bg-white text-zinc-900 dark:border-white dark:bg-zinc-800 dark:text-white'
                                : 'border-transparent bg-zinc-50 text-zinc-500 hover:text-zinc-900 dark:bg-zinc-900/60 dark:text-zinc-400 dark:hover:text-white'"
                        >
                            <div class="text-xs" x-text="item.label"></div>
                            <div class="mt-0.5 truncate text-xs opacity-70" x-text="item.hint"></div>
                        </button>
                    </template>
                </div>

                {{-- Contenu des onglets --}}
                <div class="min-h-[22rem] p-4">
                    <div x-show="tab === 'tickets'">
                        <livewire:pages::dashboard.tickets-chart />
                    </div>

                    <div x-show="tab === 'partition'" x-cloak>
                        <livewire:pages::dashboard.partition-chart />
                    </div>

                    <div x-show="tab === 'repartition'" x-cloak>
                        <livewire:pages::dashboard.donut-chart />
                    </div>

                    <div x-show="tab === 'activite'" x-cloak>
                        <livewire:pages::dashboard.user-activity-chart />
                    </div>

                    <div x-show="tab === 'utilisateurs'" x-cloak>
                        <livewire:pages::dashboard.user-activity-table />
                    </div>
                </div>

                {{-- Pied : description de l'onglet actif --}}
                <div class="border-t border-zinc-200 bg-zinc-50 px-5 py-3 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900/60 dark:text-zinc-400"
                     x-text="tabs[tab].hint"></div>
            </div>
        </section>
    </div>
</x-layouts::app>
