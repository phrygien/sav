<div>
    <flux:main>
        {{-- En-tête : empilé sur mobile, sur une ligne à partir de lg --}}
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between mb-6">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item href="#" divider="slash">Tickets</flux:breadcrumbs.item>
                <flux:breadcrumbs.item href="#" divider="slash">Liste des tickets</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            {{-- Barre d'outils : colonne sur mobile, wrap sur tablette, ligne sur desktop --}}
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center lg:flex-nowrap">

                {{-- Filtres : côte à côte, chacun prend 50% sur mobile --}}
                <div class="flex gap-2 w-full sm:w-auto">
                    <flux:select size="sm" placeholder="Choose industry..." class="flex-1 sm:w-44 sm:flex-none">
                        <flux:select.option>Photography</flux:select.option>
                        <flux:select.option>Design services</flux:select.option>
                        <flux:select.option>Web development</flux:select.option>
                        <flux:select.option>Accounting</flux:select.option>
                        <flux:select.option>Legal services</flux:select.option>
                        <flux:select.option>Consulting</flux:select.option>
                        <flux:select.option>Other</flux:select.option>
                    </flux:select>

                    <flux:input icon="magnifying-glass" placeholder="Rechercher..." size="sm" class="flex-1 sm:w-48 sm:flex-none" />
                </div>

                {{-- Segmenté maison : pleine largeur sur mobile, scrollable si trop étroit (plus de max-md:hidden) --}}
                <div
                    x-data="{ view: 'cosma' }"
                    class="flex w-full sm:w-auto items-center gap-1 overflow-x-auto rounded-lg bg-zinc-100 dark:bg-zinc-800 p-1"
                >
                    @foreach (['cosma' => 'COSMA', 'digiparf' => 'DIGIPARF', 'kalista' => 'KALISTA'] as $key => $label)
                        <button
                            type="button"
                            @click="view = '{{ $key }}'"
                            :class="view === '{{ $key }}'
                                ? 'bg-white dark:bg-zinc-700 text-zinc-800 dark:text-white shadow-sm'
                                : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                            class="flex-1 sm:flex-none whitespace-nowrap px-3 py-1 text-sm font-medium rounded-md transition-colors"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                {{-- Équipe + Invite : sur sa propre ligne en mobile, poussé à droite dès sm --}}
                <div class="flex items-center justify-between gap-3 sm:ml-auto lg:ml-0">
                    <flux:separator vertical class="my-2 max-sm:hidden" />

                    <flux:avatar.group class="**:ring-white dark:**:ring-zinc-800">
                        @foreach (['Caleb Porzio', 'River Porzio', 'Knox Porzio'] as $item)
                            <flux:avatar size="sm" tooltip name="{{ $item }}" src="https://i.pravatar.cc/100?img={{ $loop->index + 12 }}" />
                        @endforeach

                        <flux:avatar size="sm">3+</flux:avatar>
                    </flux:avatar.group>

                    <flux:button variant="filled" size="sm">Invite</flux:button>
                </div>
            </div>
        </div>

        {{-- Board : scroll horizontal avec snap sur mobile --}}
        <div class="overflow-x-auto -mx-6 px-6 pb-4 snap-x snap-mandatory sm:snap-none scroll-px-6">
            <div class="flex gap-4 w-max">
                @foreach ($this->columns as $column)
                    {{-- Largeur : 85% de l'écran sur mobile (on devine la colonne suivante), 20rem dès sm --}}
                    <div class="snap-start shrink-0 w-[85vw] max-w-80 sm:w-80">
                        <div class="rounded-lg bg-zinc-400/5 dark:bg-zinc-900">
                            <div class="px-4 py-4 flex justify-between items-start gap-2">
                                <div class="min-w-0">
                                    <flux:heading class="truncate">{{ $column['title'] }}</flux:heading>
                                    <flux:text class="mb-0! mt-2">{{ count($column['cards']) }} tasks</flux:text>
                                </div>
                                <flux:button variant="subtle" icon="ellipsis-horizontal" size="sm" />
                            </div>

                            <div class="flex flex-col gap-2 px-2">
                                @foreach ($column['cards'] as $card)
                                    <div class="bg-white rounded-lg shadow-xs border border-zinc-200 dark:border-white/10 dark:bg-zinc-800 p-3 space-y-2">
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($card['badges'] as $badge)
                                                <flux:badge :color="$badge['color']" size="sm">{{ $badge['title'] }}</flux:badge>
                                            @endforeach
                                        </div>

                                        <flux:heading class="break-words">{{ $card['title'] }}</flux:heading>

                                        @if (!empty($card['assignees']))
                                            <div class="flex justify-end">
                                                <flux:avatar.group class="**:ring-white dark:**:ring-zinc-800">
                                                    @foreach (array_slice($card['assignees'], 0, 3) as $assignee)
                                                        <flux:avatar
                                                            size="xs"
                                                            tooltip="{{ $assignee['name'] }}"
                                                            :src="$assignee['src'] ?? null"
                                                            :name="$assignee['name']"
                                                            :color="$this->colorForName($assignee['name'])"
                                                        />
                                                    @endforeach

                                                    @if (count($card['assignees']) > 3)
                                                        <flux:avatar size="xs">{{ count($card['assignees']) - 3 }}+</flux:avatar>
                                                    @endif
                                                </flux:avatar.group>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            <div class="px-2 py-2">
                                <flux:button variant="subtle" icon="plus" size="sm" class="w-full justify-start!">New task</flux:button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </flux:main>
</div>
