<div>
    @php
        $segments = ['all' => 'Tous'] + collect($projects)->mapWithKeys(fn ($p) => [$p['id'] => $p['name']])->all();
    @endphp

    {{-- En-tête : empilé sur mobile, sur une ligne à partir de lg --}}
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="#" divider="slash">Tickets</flux:breadcrumbs.item>
            <flux:breadcrumbs.item href="#" divider="slash">Liste des tickets</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        {{-- Barre d'outils : colonne sur mobile, wrap sur tablette, ligne sur desktop --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center lg:flex-nowrap">
            <div class="flex w-full gap-2 sm:w-auto">
                <flux:select wire:model.live="labelId" size="sm" class="flex-1 sm:w-48 sm:flex-none">
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

            {{-- Segmenté par projet --}}
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
        </div>
    </div>

    {{-- Board : drag & drop Alpine --}}
    <div
        x-data="{ dragging: null, from: null, over: null, notice: null, timer: null }"
        @ticket-moved.window="
            notice = $event.detail.message;
            clearTimeout(timer);
            timer = setTimeout(() => notice = null, 2500);
        "
    >
        {{-- Barre de statut du drag & drop --}}
        <div class="mb-3 flex h-6 items-center text-sm" aria-live="polite">
            {{-- 1. Pendant le drag --}}
            <div x-show="dragging !== null" x-cloak class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
                <flux:icon.arrows-right-left variant="mini" class="size-4" />
                <span>{{ __('Déposez le ticket dans une colonne pour changer son statut') }}</span>
            </div>

            {{-- 2. Pendant l'appel API --}}
            <div
                wire:loading.flex
                wire:target="moveTicket"
                class="items-center gap-2 text-blue-600 dark:text-blue-400"
            >
                <flux:icon.loading variant="mini" class="size-4" />
                <span>{{ __('Mise à jour du statut en cours…') }}</span>
            </div>

            {{-- 3. Confirmation après succès --}}
            <div
                x-show="notice && dragging === null"
                x-cloak
                x-transition.opacity
                wire:loading.remove
                wire:target="moveTicket"
                class="flex items-center gap-2 text-green-600 dark:text-green-400"
            >
                <flux:icon.check-circle variant="mini" class="size-4" />
                <span x-text="notice"></span>
            </div>
        </div>

        {{-- Scroll horizontal (mobile) uniquement --}}
        <div
            wire:loading.class="opacity-50"
            wire:target="setProject,labelId,search"
            class="snap-x snap-mandatory overflow-x-auto pb-4 transition-opacity sm:snap-none"
        >
            {{-- Hauteur fixe : ce sont les colonnes qui scrollent, pas la page --}}
            <div class="flex h-[calc(100dvh-17rem)] min-h-96 w-max gap-4 sm:w-full">
                @foreach ($this::STATUSES as $status => $meta)
                    @php
                        $column    = $columns[$status] ?? ['tickets' => [], 'page' => 1, 'lastPage' => 1, 'total' => 0, 'error' => null];
                        $remaining = max($column['total'] - count($column['tickets']), 0);
                        $hasMore   = $remaining > 0 && ! $column['error'] && $column['page'] < $column['lastPage'];
                    @endphp

                    <div wire:key="col-{{ $status }}" class="h-full w-[85vw] max-w-80 shrink-0 snap-start sm:min-w-72 sm:max-w-none sm:flex-1">
                        {{-- Zone de dépôt --}}
                        <div
                            class="flex h-full flex-col rounded-lg bg-zinc-400/5 transition-colors dark:bg-zinc-900"
                            :class="over === '{{ $status }}' && from !== '{{ $status }}' && 'ring-2 ring-blue-400/60 bg-blue-500/5'"
                            @dragover.prevent="over = '{{ $status }}'"
                            @dragleave="if (! $el.contains($event.relatedTarget)) over = null"
                            @drop.prevent="
                                if (dragging !== null && from !== '{{ $status }}') {
                                    $wire.moveTicket(dragging, '{{ $status }}')
                                }
                                dragging = null; from = null; over = null
                            "
                        >
                            {{-- En-tête de colonne (fixe) --}}
                            <div class="flex shrink-0 items-start justify-between gap-2 px-4 py-4">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="size-2 rounded-full {{ $meta['dot'] }}"></span>
                                        <flux:heading class="truncate">{{ $meta['title'] }}</flux:heading>
                                    </div>
                                    <flux:text class="mb-0! mt-2">{{ number_format($column['total'], 0, ',', ' ') }} tickets</flux:text>
                                </div>
                            </div>

                            {{-- Liste scrollable : gap-4 = espace entre les cartes, px-6 = largeur des cartes réduite --}}
                            <div class="flex min-h-16 flex-1 flex-col gap-4 overflow-y-auto overscroll-contain px-10 pb-3">
                                @if ($column['error'])
                                    <flux:callout variant="danger" icon="exclamation-circle" :heading="$column['error']" />
                                @elseif (empty($column['tickets']))
                                    <flux:text class="py-6 text-center">{{ __('Aucun ticket') }}</flux:text>
                                @endif

                                @foreach ($column['tickets'] as $card)
                                    <div
                                        wire:key="card-{{ $card['id'] }}"
                                        draggable="true"
                                        @dragstart="
                                            dragging = @js($card['id']);
                                            from = '{{ $status }}';
                                            $event.dataTransfer.effectAllowed = 'move';
                                            $event.dataTransfer.setData('text/plain', String(dragging));
                                        "
                                        @dragend="dragging = null; from = null; over = null"
                                        :class="dragging === @js($card['id']) && 'opacity-40'"
                                        class="shrink-0 cursor-grab space-y-2 rounded-lg border border-zinc-200 bg-white p-3 shadow-xs active:cursor-grabbing dark:border-white/10 dark:bg-zinc-800"
                                    >
                                        <div class="flex flex-wrap gap-2">
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

                                        <flux:heading class="break-words">{{ $card['subject'] ?: __('(sans objet)') }}</flux:heading>

                                        <flux:text class="text-xs">
                                            {{ $card['num'] }}@if ($card['date']) • {{ $card['date'] }}@endif
                                            @if ($card['order']) • {{ __('Cmd') }} {{ $card['order'] }}@endif
                                        </flux:text>

                                        <div class="flex items-center justify-between gap-2">
                                            <flux:text class="min-w-0 truncate text-xs">{{ $card['client'] }}</flux:text>

                                            <flux:avatar
                                                size="xs"
                                                tooltip="{{ $card['client'] }}"
                                                :name="$card['client']"
                                                :color="$this->colorForName($card['client'])"
                                            />
                                        </div>
                                    </div>
                                @endforeach

                                {{-- Sentinelle du scroll infini.
                                     La clé change à chaque chargement : l'élément est recréé et l'observateur
                                     se redéclenche si la colonne n'est pas encore remplie. --}}
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
