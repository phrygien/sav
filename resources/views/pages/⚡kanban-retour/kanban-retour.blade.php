
<div wire:init="loadData">
    @php
        $isAdmin  = $this->allowedProjectIds === null;
        $segments = ($isAdmin ? ['all' => 'Tous'] : [])
        + collect($projects)->mapWithKeys(fn ($p) => [$p['id'] => $p['name']])->all();

        // Couleurs d'accent par statut (classes littérales pour que Tailwind les détecte)
        $themes = [
        'green'   => ['accent' => 'text-emerald-600 dark:text-emerald-400', 'dot' => 'bg-emerald-400'],
        'blue'    => ['accent' => 'text-sky-600 dark:text-sky-400',         'dot' => 'bg-sky-400'],
        'orange'  => ['accent' => 'text-orange-600 dark:text-orange-400',   'dot' => 'bg-orange-400'],
        'default' => ['accent' => 'text-purple-600 dark:text-purple-400',   'dot' => 'bg-purple-400'],
        ];

        $users     = $this->users;
        $meId      = $this->meId;
        $canTake   = $ready && $meId !== null && isset($users[$meId]); // seuls les utilisateurs treating = 1
        $canAssign = $this->canAssign;                                  // super_admin / admin uniquement
    @endphp

    {{-- NEW : panneau de répartition (admin / super_admin uniquement) --}}
    @if ($isAdmin)
        <livewire:distribution />
    @endif

    {{-- Panneau gris clair : équipe + filtres dans une carte blanche --}}
    <div class="mb-6 rounded-2xl border border-zinc-200/70 bg-zinc-50 px-[3px] pb-[3px] dark:border-zinc-700/50 dark:bg-zinc-900/60">
        {{-- En-tête : titre coloré + description grise --}}
        <div class="space-y-1 px-4 pb-3 pt-3">
            <div class="text-xs font-semibold uppercase tracking-wide text-purple-600 dark:text-purple-400">{{ __('Equipes') }}</div>

            @if ($ready)
                <flux:text class="m-0! text-xs leading-snug! text-zinc-500! dark:text-zinc-400!">
                    {{ count($users) }} {{ count($users) > 1 ? __('membres') : __('membre') }}
                </flux:text>
            @else
                <div class="h-3 w-16 animate-pulse rounded bg-zinc-200 dark:bg-zinc-700"></div>
            @endif
        </div>

        {{-- Carte blanche : équipe à gauche, filtres à droite (empilé sur mobile, ligne à partir de lg) --}}
        <div class="flex flex-col gap-4 rounded-xl border border-zinc-300/70 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800 lg:flex-row lg:items-center lg:justify-between">
            {{-- Liste d'équipe + mes tickets --}}
            <div class="flex flex-wrap items-center gap-3">
                @if ($ready)
                    <flux:avatar.group class="**:ring-white dark:**:ring-zinc-800">
                        @foreach (collect($users)->take(3) as $uid => $uname)
                            <flux:avatar size="sm" tooltip :name="$uname" :color="$this->colorForName($uname)" />
                        @endforeach

                        @if (count($users) > 3)
                            <flux:avatar size="sm">{{ count($users) - 3 }}+</flux:avatar>
                        @endif
                    </flux:avatar.group>
                @else
                    {{-- Squelette avatars --}}
                    <div class="flex -space-x-2 animate-pulse">
                        <div class="size-8 rounded-full bg-zinc-200 ring-2 ring-white dark:bg-zinc-700 dark:ring-zinc-800"></div>
                        <div class="size-8 rounded-full bg-zinc-200 ring-2 ring-white dark:bg-zinc-700 dark:ring-zinc-800"></div>
                        <div class="size-8 rounded-full bg-zinc-200 ring-2 ring-white dark:bg-zinc-700 dark:ring-zinc-800"></div>
                    </div>
                @endif

                <flux:separator vertical class="my-1 hidden sm:block" />

                <flux:button
                    wire:click="toggleMine"
                    :variant="$mine ? 'primary' : 'outline'"
                    icon="user"
                    size="sm"
                    :disabled="! $ready"
                >
                    {{ __('Ticket qui m’est assigné') }}

                    {{-- Total des tickets assignés à l'utilisateur (tous statuts, projet affiché) --}}
                    @if ($ready && $mineCount !== null)
                        <span class="ml-1.5 inline-flex min-w-5 items-center justify-center rounded-full bg-black/10 px-1.5 text-xs font-semibold leading-5 dark:bg-white/20">
                            {{ number_format($mineCount, 0, ',', ' ') }}
                        </span>
                    @endif
                </flux:button>
            </div>

            {{-- Barre d'outils : colonne sur mobile, wrap sur tablette, ligne sur desktop --}}
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
                @if (! $ready)
                    @if ($isAdmin)
                        <div class="h-9 w-full animate-pulse rounded-lg bg-zinc-100 dark:bg-zinc-700 sm:w-56"></div>
                    @endif
                @elseif ($isAdmin || count($segments) > 1)
                    <div class="flex w-full items-center gap-1 overflow-x-auto rounded-lg bg-zinc-100 p-1 dark:bg-zinc-700/60 sm:w-auto">
                        @foreach ($segments as $id => $label)
                            <button
                                type="button"
                                wire:key="seg-{{ $id }}"
                                wire:click="setProject('{{ $id }}')"
                                class="flex-1 whitespace-nowrap rounded-md px-3 py-1 text-sm font-medium transition-colors sm:flex-none
                                    {{ (string) $projectId === (string) $id
                                        ? 'bg-white text-zinc-800 shadow-sm dark:bg-zinc-600 dark:text-white'
                                        : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Erreur de lecture des projets affectés / aucun projet (uniquement après le chargement) --}}
    @if ($ready)
        @if ($this->projectsError)
            <flux:callout
                class="mb-4"
                variant="danger"
                icon="exclamation-circle"
                :heading="__('Impossible de lire vos projets')"
                :text="$this->projectsError"
            />
        @elseif (! $isAdmin && empty($projects))
            <flux:callout
                class="mb-4"
                icon="information-circle"
                :heading="__('Aucun projet ne vous est affecté.')"
                :text="__('Contactez un administrateur pour accéder aux tickets.')"
            />
        @endif
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
                wire:target="moveTicket,assignTicket,takeTicket"
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
                wire:target="moveTicket,assignTicket,takeTicket"
                class="flex items-center gap-2 text-green-600 dark:text-green-400"
            >
                <flux:icon.check-circle variant="mini" class="size-4" />
                <span x-text="notice"></span>
            </div>
        </div>

        {{-- CHANGED : filterByUser ajouté au wire:target --}}
        <div
            wire:loading.class="opacity-50"
            wire:target="setProject,labelId,search,toggleMine,filterByUser"
            class="snap-x snap-mandatory overflow-x-auto pb-4 transition-opacity sm:snap-none"
        >
            <div class="flex h-[calc(100dvh-17rem)] min-h-96 w-max gap-6 sm:w-full">
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
                        {{-- Zone de dépôt : panneau gris clair (en-tête + carte blanche contenant la liste) --}}
                        <div
                            class="flex h-full flex-col rounded-2xl border border-zinc-200/70 bg-zinc-50 px-1 pb-1 transition-colors dark:border-zinc-700/50 dark:bg-zinc-900/60"
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
                            {{-- En-tête de colonne (fixe) : titre coloré + description grise --}}
                            <div class="shrink-0 space-y-1 px-6 pb-4 pt-5">
                                <div class="flex min-w-0 items-center gap-2 text-sm font-medium {{ $theme['accent'] }}">
                                    <span class="size-2 shrink-0 rounded-full {{ $theme['dot'] }}"></span>
                                    <span class="truncate">{{ $meta['title'] }}</span>
                                </div>

                                @if ($ready)
                                    <flux:text class="m-0! text-xs leading-snug! text-zinc-500! dark:text-zinc-400!">
                                        {{ number_format($column['total'], 0, ',', ' ') }} {{ $column['total'] > 1 ? __('tickets') : __('ticket') }}
                                    </flux:text>
                                @else
                                    <div class="h-3 w-16 animate-pulse rounded bg-zinc-200 dark:bg-zinc-700"></div>
                                @endif
                            </div>

                            {{-- Carte blanche : liste scrollable --}}
                            <div class="flex min-h-16 flex-1 flex-col gap-4 overflow-y-auto overscroll-contain rounded-xl border border-zinc-300/70 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
                                @if (! $ready)
                                    {{-- Squelettes de cartes pendant le chargement initial (4 par statut) --}}
                                    @foreach (range(1, 4) as $i)
                                        <div wire:key="skeleton-{{ $status }}-{{ $i }}" class="shrink-0 animate-pulse rounded-2xl border border-zinc-200/70 bg-zinc-50 px-[3px] pb-[3px] dark:border-zinc-700/50 dark:bg-zinc-900/60">
                                            <div class="space-y-2 px-4 pb-3 pt-3">
                                                <div class="h-4 w-20 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                                                <div class="h-3 w-2/3 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                                            </div>
                                            <div class="flex items-center justify-between gap-3 rounded-xl border border-zinc-300/70 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800">
                                                <div class="w-3/5 space-y-2">
                                                    <div class="h-4 w-full rounded bg-zinc-200 dark:bg-zinc-700"></div>
                                                    <div class="h-3 w-1/2 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                                                </div>
                                                <div class="h-7 w-20 rounded-lg bg-zinc-200 dark:bg-zinc-700"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                @else
                                    @if ($column['error'])
                                        <flux:callout variant="danger" icon="exclamation-circle" :heading="$column['error']" />
                                    @elseif (empty($column['tickets']))
                                        <flux:text class="py-6 text-center">{{ __('Aucun ticket') }}</flux:text>
                                    @endif

                                    @foreach ($column['tickets'] as $card)
                                        @php
                                            $assigneeId   = isset($card['assignee_id']) ? (int) $card['assignee_id'] : null;
                                            $assigneeName = $card['assignee'] ?? ($assigneeId ? ($users[$assigneeId] ?? null) : null);

                                            $title = $card['label'] ?: $card['num'];
                                            $ref   = collect([
                                            $card['label'] ? $card['num'] : null,
                                            $card['order'] ? __('Cmd') . ' ' . $card['order'] : null,
                                            ])->filter()->implode(' • ');
                                        @endphp

                                        {{-- Panneau gris clair (cliquable + draggable) : en-tête + carte blanche --}}
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
                                            class="group shrink-0 cursor-pointer rounded-2xl border border-zinc-200/70 bg-zinc-50 px-[3px] pb-[3px] transition focus-visible:outline-2 focus-visible:outline-blue-500 active:cursor-grabbing dark:border-zinc-700/50 dark:bg-zinc-900/60"
                                        >
                                            {{-- En-tête : catégorie + badges, puis référence, puis date de création --}}
                                            <div class="space-y-1 px-4 pb-3 pt-3">
                                                <div class="flex items-center justify-between gap-3">
                                                    <div class="min-w-0 truncate text-xs font-semibold uppercase tracking-wide {{ $theme['accent'] }}">{{ $title }}</div>

                                                    @if ($card['attention'] || ($projectId === 'all' && $card['project']))
                                                        <div class="flex shrink-0 flex-wrap items-center justify-end gap-1">
                                                            @if ($card['attention'])
                                                                <flux:badge color="amber" size="sm" icon="chat-bubble-left-ellipsis">
                                                                    {{ __('Client a répondu') }}
                                                                </flux:badge>
                                                            @endif

                                                            @if ($projectId === 'all' && $card['project'])
                                                                <flux:badge color="zinc" size="sm">{{ $card['project'] }}</flux:badge>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>

                                                @if ($ref !== '')
                                                    <flux:text class="m-0! text-xs leading-snug! text-zinc-500! dark:text-zinc-400!">{{ $ref }}</flux:text>
                                                @endif

                                                @if ($card['date'])
                                                    <flux:text class="m-0! whitespace-nowrap text-xs italic leading-snug! text-zinc-400! dark:text-zinc-500!">
                                                        {{ __('Créé le') }} {{ $card['date'] }}@if ($card['time']) {{ __('à') }} {{ $card['time'] }}@endif
                                                    </flux:text>
                                                @endif
                                            </div>

                                            {{-- Carte blanche : texte à gauche, actions (boutons outline) à droite --}}
                                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-300/70 bg-white px-4 py-3 transition group-hover:border-zinc-400/70 dark:border-zinc-700 dark:bg-zinc-800 dark:group-hover:border-zinc-600">
                                                <div class="min-w-0 flex-1 basis-32 space-y-0.5">
                                                    <flux:heading class="m-0! break-words text-sm! font-medium! leading-snug! text-zinc-900! dark:text-white!">{{ $card['subject'] ?: __('(sans objet)') }}</flux:heading>

                                                    <flux:text class="m-0! line-clamp-2 text-xs leading-snug! text-zinc-400! dark:text-zinc-500!">{{ $card['client'] }}</flux:text>
                                                </div>

                                                {{-- .stop : évite de déclencher la navigation de la carte --}}
                                                <div class="flex shrink-0 items-center gap-1.5" @click.stop @keydown.enter.stop @keydown.space.stop draggable="false">
                                                    @if ($assigneeName)
                                                        <flux:avatar
                                                            size="xs"
                                                            tooltip="{{ __('Assigné à :name', ['name' => $assigneeName]) }}"
                                                            :name="$assigneeName"
                                                            :color="$this->colorForName($assigneeName)"
                                                        />
                                                    @endif

                                                    {{-- Prendre le ticket : tout utilisateur treating = 1 --}}
                                                    @if ($canTake)
                                                        <flux:button
                                                            wire:key="take-{{ $card['id'] }}"
                                                            wire:click="takeTicket('{{ $card['id'] }}')"
                                                            size="xs"
                                                            variant="outline"
                                                            :icon="$meId !== null && $assigneeId === $meId ? 'check-circle' : 'hand-raised'"
                                                            :disabled="$assigneeId === $meId"
                                                            aria-label="{{ __('Prendre le ticket') }}"
                                                            class="{{ $theme['accent'] }}!"
                                                        >
                                                            {{ $meId !== null && $assigneeId === $meId ? __('Pris') : __('Prendre') }}
                                                        </flux:button>
                                                    @endif

                                                    {{-- Assigner à quelqu'un : super_admin / admin uniquement --}}
                                                    @if ($canAssign)
                                                        <flux:dropdown position="bottom" align="end">
                                                            <flux:button
                                                                size="xs"
                                                                variant="outline"
                                                                icon="user-plus"
                                                                tooltip="{{ __('Assigner') }}"
                                                                aria-label="{{ __('Assigner le ticket') }}"
                                                                class="{{ $theme['accent'] }}!"
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
                                                    @endif
                                                </div>
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
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
