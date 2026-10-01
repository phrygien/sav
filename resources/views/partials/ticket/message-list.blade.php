{{-- Card 1 : liste des messages. Attend : $messages, $metas, $selectedMessageIndex --}}
<div class="flex max-h-[70dvh] min-h-0 flex-col overflow-hidden rounded-lg border border-zinc-200 bg-zinc-400/5 lg:max-h-none dark:border-white/10 dark:bg-zinc-900">
    <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 px-4 py-4">
        <div class="flex items-center gap-2">
            <flux:heading>{{ __('Conversation') }}</flux:heading>
            <span class="rounded-full bg-zinc-200/70 px-2 py-0.5 text-xs font-medium text-zinc-600 dark:bg-white/10 dark:text-zinc-300">
                {{ count($messages) }}
            </span>
        </div>
    </div>

    <div class="min-h-0 flex-1 space-y-2 overflow-y-auto overscroll-contain px-4 pb-4">
        @forelse ($messages as $idx => $msg)
            @php
                $meta       = $metas[$idx];
                $isSelected = $selectedMessageIndex === $idx;
            @endphp

            <div
                wire:key="msg-{{ $idx }}"
                wire:click="openMessage({{ $idx }})"
                class="cursor-pointer space-y-2 rounded-lg bg-white px-3.5 py-3 shadow-[0_1px_3px_rgba(15,15,15,0.08)] transition hover:bg-zinc-50 hover:shadow-[0_2px_6px_rgba(15,15,15,0.10)] dark:bg-zinc-800 dark:hover:bg-zinc-700/70
                    {{ $isSelected
                        ? 'ring-2 ring-blue-400/60 dark:ring-blue-400/50'
                        : 'ring-1 ring-black/[0.03] dark:ring-white/5' }}"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <flux:avatar size="sm" :name="$meta['name']" color="auto" :color:seed="$meta['name']" />

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $meta['name'] }}</span>

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

                <p class="truncate text-sm font-normal text-zinc-900 dark:text-white">{{ $meta['subject'] }}</p>
                <p class="line-clamp-2 text-xs text-zinc-500 dark:text-zinc-400">{{ $meta['preview'] }}</p>
            </div>
        @empty
            <div class="flex h-full flex-col items-center justify-center gap-3 p-8 text-center">
                <i class="hgi-stroke hgi-mail-01 text-zinc-300" style="width:3rem;height:3rem;font-size:3rem"></i>
                <flux:text>{{ __('Aucun message trouvé') }}</flux:text>
            </div>
        @endforelse
    </div>
</div>
