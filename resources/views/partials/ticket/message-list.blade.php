{{-- Card 1 : liste des messages. Attend : $messages, $metas, $selectedMessageIndex --}}
@php
    // Même style de carte que tab-description (à garder synchronisé)
    $card      = 'overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50 dark:border-white/10 dark:bg-zinc-900';
    $panel     = 'mx-1.5 mb-1.5 overflow-hidden rounded-xl border border-zinc-300/70 bg-white dark:border-white/10 dark:bg-zinc-800';

    // Carte d'un message : même carte, seule la couleur de bordure change quand elle est sélectionnée.
    // Pas de $card ici pour éviter deux couleurs de bordure en conflit.
    $itemBase     = 'overflow-hidden rounded-2xl border bg-zinc-50 cursor-pointer transition dark:bg-zinc-900';
    $itemSelected = 'border-zinc-400 dark:border-white/40';
    $itemIdle     = 'border-zinc-200 hover:border-zinc-300 dark:border-white/10 dark:hover:border-white/20';
@endphp

<div class="flex max-h-[70dvh] min-h-0 flex-col lg:max-h-none">

    {{-- En-tête (sans carte) --}}
    <div class="flex shrink-0 items-center justify-between gap-3 px-1 pb-4">
        <div class="min-w-0">
            <div class="text-sm font-bold text-zinc-900 dark:text-white">{{ __('Conversation') }}</div>
            <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Messages échangés avec le client.') }}</div>
        </div>

        <span class="rounded-full bg-zinc-200/70 px-2 py-0.5 text-xs font-bold text-zinc-600 dark:bg-white/10 dark:text-zinc-300">
            {{ count($messages) }}
        </span>
    </div>

    {{-- Liste : une carte par message, seule cette zone défile --}}
    <div class="min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain pb-1">
        @forelse ($messages as $idx => $msg)
            @php
                $meta       = $metas[$idx];
                $isSelected = $selectedMessageIndex === $idx;
            @endphp

            <div
                wire:key="msg-{{ $idx }}"
                wire:click="openMessage({{ $idx }})"
                class="{{ $itemBase }} {{ $isSelected ? $itemSelected : $itemIdle }}"
            >
                {{-- En-tête de la carte : expéditeur + date --}}
                <div class="flex items-start justify-between gap-2 px-4 py-3.5">
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <flux:avatar size="sm" :name="$meta['name']" color="auto" :color:seed="$meta['name']" />

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="truncate text-sm font-bold text-zinc-900 dark:text-white">{{ $meta['name'] }}</span>

                                @if ($meta['role'] === 'client')
                                    <flux:badge color="blue" size="sm">{{ __('Client') }}</flux:badge>
                                @elseif ($meta['role'] === 'support')
                                    <flux:badge color="green" size="sm">{{ __('Support') }}</flux:badge>
                                @endif
                            </div>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $meta['date'] }}</p>
                        </div>
                    </div>

                    @if (! empty($msg['attachments']))
                        <i class="hgi-stroke hgi-attachment-01 shrink-0 text-zinc-400"></i>
                    @endif
                </div>

                {{-- Panneau blanc : objet + aperçu --}}
                <div class="{{ $panel }} space-y-1 px-4 py-3">
                    <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $meta['subject'] }}</p>
                    <p class="line-clamp-2 text-xs text-zinc-500 dark:text-zinc-400">{{ $meta['preview'] }}</p>
                </div>
            </div>
        @empty
            <div class="{{ $card }}">
                <div class="{{ $panel }} mt-1.5 flex flex-col items-center justify-center gap-2 px-6 py-12 text-center">
                    <i class="hgi-stroke hgi-mail-01 text-zinc-300 dark:text-zinc-600" style="width:3rem;height:3rem;font-size:3rem"></i>
                    <div class="text-sm font-bold text-zinc-900 dark:text-white">{{ __('Aucun message') }}</div>
                    <div class="text-sm text-zinc-400 dark:text-zinc-500">{{ __('Aucun message trouvé') }}</div>
                </div>
            </div>
        @endforelse
    </div>
</div>
