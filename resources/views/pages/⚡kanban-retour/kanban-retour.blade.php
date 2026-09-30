<div>
    @php
        $isAdmin  = $this->allowedProjectIds === null;
        $segments = ($isAdmin ? ['all' => 'Tous'] : [])
            + collect($projects)->mapWithKeys(fn ($p) => [$p['id'] => $p['name']])->all();

        // Thèmes pastel (classes littérales pour que Tailwind les détecte)
        $themes = [
            'green' => [
                'column' => 'bg-emerald-500/[0.04] dark:bg-emerald-400/[0.06]',
                'pill'   => 'bg-emerald-100/80 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300',
                'dot'    => 'bg-emerald-400',
                'count'  => 'text-emerald-500 dark:text-emerald-300',
            ],
            'blue' => [
                'column' => 'bg-sky-500/[0.04] dark:bg-sky-400/[0.06]',
                'pill'   => 'bg-sky-100/80 text-sky-700 dark:bg-sky-400/15 dark:text-sky-300',
                'dot'    => 'bg-sky-400',
                'count'  => 'text-sky-500 dark:text-sky-300',
            ],
            'orange' => [
                'column' => 'bg-orange-500/[0.04] dark:bg-orange-400/[0.06]',
                'pill'   => 'bg-orange-100/80 text-orange-700 dark:bg-orange-400/15 dark:text-orange-300',
                'dot'    => 'bg-orange-400',
                'count'  => 'text-orange-500 dark:text-orange-300',
            ],
            'default' => [
                'column' => 'bg-purple-500/[0.04] dark:bg-purple-400/[0.06]',
                'pill'   => 'bg-purple-100/80 text-purple-700 dark:bg-purple-400/15 dark:text-purple-300',
                'dot'    => 'bg-purple-400',
                'count'  => 'text-purple-500 dark:text-purple-300',
            ],
        ];

        $users = $this->users;
        $meId  = $this->meId;
        $canTake = $meId !== null && isset($users[$meId]); // seuls les utilisateurs treating = 1
    @endphp

    {{-- En-tête --}}
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="#" divider="slash">Equipes </flux:breadcrumbs.item>

            <flux:separator vertical class="my-2" />

            <flux:avatar.group class="**:ring-white dark:**:ring-zinc-800">
                @foreach (collect($users)->take(3) as $uid => $uname)
                    <flux:avatar size="sm" tooltip :name="$uname" :color="$this->colorForName($uname)" />
                @endforeach

                @if (count($users) > 3)
                    <flux:avatar size="sm">{{ count($users) - 3 }}+</flux:avatar>
                @endif
            </flux:avatar.group>

            <flux:button
                wire:click="toggleMine"
                :variant="$mine ? 'primary' : 'filled'"
                icon="user"
                size="sm"
                class="ml-5"
            >
                {{ __('Ticket qui m’est assigné') }}
            </flux:button>
        </flux:breadcrumbs>

        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center lg:flex-nowrap">
            <div class="flex w-full gap-2 sm:w-auto">
                <flux:select wire:model.live="labelId" size="sm" class="flex-1 sm:w-48 sm:flex-none" disabled>
                    <flux:select.option value="">{{ __('Toutes catégories') }}</flux:select.option>
                    @foreach ($this::LABELS as $id => $name)
                        <flux:select.option :value="$id">{{ $name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input
                    wire:model.live.debounce.400ms="search"
                    icon="magnifying-glass"
                    placeholder="Rechercher..."
                    size="sm"
                    class="flex-1 sm:w-48 sm:flex-none"
                />
            </div>

            {{-- Segmenté par projet (masqué pour un non-admin avec un seul projet) --}}
            @if ($isAdmin || count($segments) > 1)
                <div class="flex w-full items-center gap-1 overflow-x-auto rounded-lg bg-zinc-100 p-1 dark:bg-zinc-800 sm:w-auto">
                    @foreach ($segments as $id => $label)
                        <button
                            type="button"
                            wire:key="seg-{{ $id }}"
                            wire:click="setProject('{{ $id }}')"
                            class="flex-1 whitespace-nowrap rounded-md px-3 py-1 text-sm font-medium transition-colors sm:flex-none
                                {{ (string) $projectId === (string) $id
                                    ? 'bg-white text-zinc-800 shadow-sm dark:bg-zinc-700 dark:text-white'
                                    : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Erreur de lecture des projets affectés --}}
    @if ($this->projectsError)
        <flux:callout
            class="mb-4"
            variant="danger"
            icon="exclamation-circle"
            :heading="__('Impossible de lire vos projets')"
            :text="$this->projectsError"
        />
    @elseif (! $isAdmin && empty($projects))
        {{-- Non-admin sans projet affecté --}}
        <flux:callout
            class="mb-4"
            icon="information-circle"
            :heading="__('Aucun projet ne vous est affecté.')"
            :text="__('Contactez un administrateur pour accéder aux tickets.')"
        />
    @endif

    {{-- Board : drag & drop Alpine --}}
    <div
        x-data="{ dragging: null, from: null, over: null, notice: null, timer: null }"
        @ticket-moved.window="
            notice = $event.detail.message;
            clearTimeout(timer);
            timer = setTimeout(() => notice = null, 2500);
        "
    >
        <div class="mb-3 flex h-6 items-center text-sm" aria-live="polite">
            <div x-show="dragging !== null" x-cloak class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
                <flux:icon.arrows-right-left variant="mini" class="size-4" />
                <span>{{ __('Déposez le ticket dans une colonne pour changer son statut') }}</span>
            </div>

            <div
                wire:loading.flex
                wire:target="moveTicket,assignTicket"
                class="items-center gap-2 text-blue-600 dark:text-blue-400"
            >
                <flux:icon.loading variant="mini" class="size-4" />
                <span>{{ __('Mise à jour en cours…') }}</span>
            </div>

            <div
                x-show="notice && dragging === null"
                x-cloak
                x-transition.opacity
                wire:loading.remove
                wire:target="moveTicket,assignTicket"
                class="flex items-center gap-2 text-green-600 dark:text-green-400"
            >
                <flux:icon.check-circle variant="mini" class="size-4" />
                <span x-text="notice"></span>
            </div>
        </div>

        <div
            wire:loading.class="opacity-50"
            wire:target="setProject,labelId,search,toggleMine"
            class="snap-x snap-mandatory overflow-x-auto pb-4 transition-opacity sm:snap-none"
        >
            <div class="flex h-[calc(100dvh-17rem)] min-h-96 w-max gap-4 sm:w-full">
                @foreach ($this::STATUSES as $status => $meta)
                    @php
                        $column    = $columns[$status] ?? ['tickets' => [], 'page' => 1, 'lastPage' => 1, 'total' => 0, 'error' => null];
                        $remaining = max($column['total'] - count($column['tickets']), 0);
                        $hasMore   = $remaining > 0 && ! $column['error'] && $column['page'] < $column['lastPage'];
                        $haystack  = \Illuminate\Support\Str::slug($status . ' ' . ($meta['title'] ?? ''));
                        $theme     = $themes[match (true) {
                            str_contains($haystack, 'clotur')  => 'green',
                            str_contains($haystack, 'cours')   => 'blue',
                            str_contains($haystack, 'attente') => 'orange',
                            default                            => 'default',
                        }];
                    @endphp

                    <div wire:key="col-{{ $status }}" class="h-full w-[85vw] max-w-80 shrink-0 snap-start sm:min-w-72 sm:max-w-none sm:flex-1">
                        <div
                            class="flex h-full flex-col rounded-lg transition-colors {{ $theme['column'] }}"
                            :class="over === '{{ $status }}' && from !== '{{ $status }}' && 'ring-2 ring-blue-400/60'"
                            @dragover.prevent="over = '{{ $status }}'"
                            @dragleave="if (! $el.contains($event.relatedTarget)) over = null"
                            @drop.prevent="
                                if (dragging !== null && from !== '{{ $status }}') {
                                    $wire.moveTicket(dragging, '{{ $status }}')
                                }
                                dragging = null; from = null; over = null
                            "
                        >
                            <div class="flex shrink-0 items-center gap-2 px-10 py-4">
                                <span class="inline-flex min-w-0 items-center gap-2 rounded-full px-2.5 py-0.5 text-sm font-medium {{ $theme['pill'] }}">
                                    <span class="size-2 shrink-0 rounded-full {{ $theme['dot'] }}"></span>
                                    <span class="truncate">{{ $meta['title'] }}</span>
                                </span>
                                <span class="text-sm font-medium {{ $theme['count'] }}">
                                    {{ number_format($column['total'], 0, ',', ' ') }}
                                </span>
                            </div>

                            <div class="flex min-h-16 flex-1 flex-col gap-4 overflow-y-auto overscroll-contain px-10 pb-3">
                                @if ($column['error'])
                                    <flux:callout variant="danger" icon="exclamation-circle" :heading="$column['error']" />
                                @elseif (empty($column['tickets']))
                                    <flux:text class="py-6 text-center">{{ __('Aucun ticket') }}</flux:text>
                                @endif

                                @foreach ($column['tickets'] as $card)
                                    @php
                                        $assigneeId   = isset($card['assignee_id']) ? (int) $card['assignee_id'] : null;
                                        $assigneeName = $card['assignee'] ?? ($assigneeId ? ($users[$assigneeId] ?? null) : null);
                                    @endphp

                                    <div
                                        wire:key="card-{{ $card['id'] }}"
                                        draggable="true"
                                        role="link"
                                        tabindex="0"
                                        @click="Livewire.navigate('{{ route('kanban.details', ['ticket' => $card['id']]) }}')"
                                        @keydown.enter="Livewire.navigate('{{ route('kanban.details', ['ticket' => $card['id']]) }}')"
                                        @dragstart="
                                            dragging = @js($card['id']);
                                            from = '{{ $status }}';
                                            $event.dataTransfer.effectAllowed = 'move';
                                            $event.dataTransfer.setData('text/plain', String(dragging));
                                        "
                                        @dragend="dragging = null; from = null; over = null"
                                        :class="dragging === @js($card['id']) && 'opacity-40'"
                                        class="shrink-0 cursor-pointer space-y-3 rounded-lg bg-white px-3.5 py-3 shadow-[0_1px_3px_rgba(15,15,15,0.08)] ring-1 ring-black/[0.03] transition hover:bg-zinc-50 hover:shadow-[0_2px_6px_rgba(15,15,15,0.10)] focus-visible:outline-2 focus-visible:outline-blue-500 active:cursor-grabbing dark:bg-zinc-800 dark:ring-white/5 dark:hover:bg-zinc-700/70"
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex flex-wrap items-center gap-2">
                                                @if ($card['label'])
                                                    <flux:badge color="blue" size="sm">{{ $card['label'] }}</flux:badge>
                                                @endif

                                                @if ($card['attention'])
                                                    <flux:badge color="amber" size="sm">{{ __('Attention') }}</flux:badge>
                                                @endif

                                                @if ($projectId === 'all' && $card['project'])
                                                    <flux:badge color="zinc" size="sm">{{ $card['project'] }}</flux:badge>
                                                @endif
                                            </div>

                                            <div class="flex shrink-0 items-center gap-0.5" @click.stop @keydown.enter.stop @keydown.space.stop draggable="false">
                                                @if ($canTake)
                                                    <flux:button
                                                        wire:key="take-{{ $card['id'] }}"
                                                        wire:click="takeTicket('{{ $card['id'] }}')"
                                                        size="xs"
                                                        variant="ghost"
                                                        :icon="$meId !== null && $assigneeId === $meId ? 'check-circle' : 'hand-raised'"
                                                        inset
                                                        :disabled="$assigneeId === $meId"
                                                        tooltip="{{ $meId !== null && $assigneeId === $meId ? __('Ticket déjà pris par moi') : __('Prendre le ticket') }}"
                                                        aria-label="{{ __('Prendre le ticket') }}"
                                                        class="text-zinc-400! hover:text-zinc-600! dark:text-zinc-500! dark:hover:text-zinc-300!"
                                                    />
                                                @endif

                                                <flux:dropdown position="bottom" align="end">
                                                    <flux:button
                                                        size="xs"
                                                        variant="ghost"
                                                        icon="user-plus"
                                                        inset
                                                        tooltip="{{ __('Assigner') }}"
                                                        aria-label="{{ __('Assigner le ticket') }}"
                                                        class="text-zinc-400! hover:text-zinc-600! dark:text-zinc-500! dark:hover:text-zinc-300!"
                                                    />

                                                    <flux:menu>
                                                        <flux:menu.group heading="{{ __('Assigner à') }}">
                                                            @forelse ($users as $userId => $userName)
                                                                <flux:menu.item
                                                                    wire:key="assign-{{ $card['id'] }}-{{ $userId }}"
                                                                    wire:click="assignTicket('{{ $card['id'] }}', {{ $userId }})"
                                                                    :icon="$assigneeId === $userId ? 'check' : null"
                                                                >
                                                                    {{ $userName }}
                                                                </flux:menu.item>
                                                            @empty
                                                                <flux:menu.item disabled>{{ __('Aucun utilisateur disponible') }}</flux:menu.item>
                                                            @endforelse
                                                        </flux:menu.group>
                                                    </flux:menu>
                                                </flux:dropdown>
                                            </div>
                                        </div>

                                        <div class="space-y-1">
                                            <flux:heading class="m-0! break-words text-sm! font-bold! leading-snug!">{{ $card['subject'] ?: __('(sans objet)') }}</flux:heading>

                                            <flux:text class="m-0! text-xs leading-snug!">
                                                {{ $card['num'] }}@if ($card['order']) • {{ __('Cmd') }} {{ $card['order'] }}@endif
                                            </flux:text>

                                            @if ($card['date'])
                                                <flux:text class="m-0! flex items-center gap-1 text-xs leading-snug!">
                                                    créé le <span>{{ $card['date'] }}@if ($card['time']) {{ __('à') }} {{ $card['time'] }}@endif</span>
                                                </flux:text>
                                            @endif
                                        </div>

                                        <div class="flex items-center justify-between gap-2">
                                            <flux:text class="min-w-0 truncate text-xs">{{ $card['client'] }}</flux:text>

                                            @if ($assigneeName)
                                                <flux:avatar
                                                    size="xs"
                                                    tooltip="{{ __('Assigné à :name', ['name' => $assigneeName]) }}"
                                                    :name="$assigneeName"
                                                    :color="$this->colorForName($assigneeName)"
                                                />
                                            @endif
                                        </div>
                                    </div>
                                @endforeach

                                @if ($hasMore)
                                    <div
                                        wire:key="sentinel-{{ $status }}-{{ count($column['tickets']) }}"
                                        x-intersect.margin.200px="$wire.loadMore('{{ $status }}')"
                                        class="flex shrink-0 items-center justify-center gap-2 py-3 text-xs text-zinc-500 dark:text-zinc-400"
                                    >
                                        <flux:icon.loading variant="mini" class="size-4" />
                                        <span>{{ __('Chargement…') }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
