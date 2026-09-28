<div>
    <flux:main>
        <div class="flex flex-col md:flex-row gap-6 justify-between md:items-center mb-6">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item href="#" divider="slash">Tickets</flux:breadcrumbs.item>
                <flux:breadcrumbs.item href="#" divider="slash">Liste des tickets</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <div class="flex gap-4">
                <flux:dropdown position="bottom" align="end">
                    <flux:button size="sm" variant="filled" icon:trailing="chevron-down">Filters</flux:button>

                    <flux:menu>
                        <flux:menu.item>Archive</flux:menu.item>
                        <flux:menu.item>Delete</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>

                {{-- Remplacement de flux:tabs (payant) par un composant segmenté maison en Alpine --}}
                <div
                    x-data="{ view: 'board' }"
                    class="inline-flex items-center gap-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 p-1 -my-px h-auto max-md:hidden"
                >
                    <button
                        type="button"
                        @click="view = 'board'"
                        :class="view === 'board'
                            ? 'bg-white dark:bg-zinc-700 text-zinc-800 dark:text-white shadow-sm'
                            : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                        class="px-3 py-1 text-sm font-medium rounded-md transition-colors"
                    >
                        Board
                    </button>

                    <button
                        type="button"
                        @click="view = 'list'"
                        :class="view === 'list'
                            ? 'bg-white dark:bg-zinc-700 text-zinc-800 dark:text-white shadow-sm'
                            : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                        class="px-3 py-1 text-sm font-medium rounded-md transition-colors"
                    >
                        List
                    </button>

                    <button
                        type="button"
                        @click="view = 'timeline'"
                        :class="view === 'timeline'
                            ? 'bg-white dark:bg-zinc-700 text-zinc-800 dark:text-white shadow-sm'
                            : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                        class="px-3 py-1 text-sm font-medium rounded-md transition-colors"
                    >
                        Timeline
                    </button>
                </div>

                <flux:separator vertical class="my-2" />

                <flux:avatar.group class="**:ring-white dark:**:ring-zinc-800">
                    @foreach (['Caleb Porzio', 'River Porzio', 'Knox Porzio'] as $item)
                        <flux:avatar size="sm" tooltip name="{{ $item }}" src="https://i.pravatar.cc/100?img={{ $loop->index + 12 }}" />
                    @endforeach

                    <flux:avatar size="sm">3+</flux:avatar>
                </flux:avatar.group>

                <flux:button variant="filled" size="sm">Invite</flux:button>
            </div>
        </div>

        <div class="overflow-x-auto -m-6 p-6">
            <div class="flex gap-4">
                @foreach ($this->columns as $column)
                    <div>
                        <div class="rounded-lg w-80 max-w-80 bg-zinc-400/5 dark:bg-zinc-900">
                            <div class="px-4 py-4 flex justify-between items-start">
                                <div>
                                    <flux:heading>{{ $column['title'] }}</flux:heading>
                                    <flux:text class="mb-0! mt-2">11 tasks</flux:text>
                                </div>
                                <flux:button variant="subtle" icon="ellipsis-horizontal" size="sm" />
                            </div>
                            <div class="flex flex-col gap-2 px-2">
                                @foreach ($column['cards'] as $card)
                                    <div class="bg-white rounded-lg shadow-xs border border-zinc-200 dark:border-white/10 dark:bg-zinc-800 p-3 space-y-2">
                                        <div class="flex gap-2">
                                            @foreach ($card['badges'] as $badge)
                                                <flux:badge :color="$badge['color']" size="sm">{{ $badge['title'] }}</flux:badge>
                                            @endforeach
                                        </div>

                                        <flux:heading>{{ $card['title'] }}</flux:heading>

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
