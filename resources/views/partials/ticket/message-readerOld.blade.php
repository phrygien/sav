{{-- Card 2 : panneau de lecture. Attend : $selected, $messages, $metas, $details, $selectedMessageIndex, $translatedMessage --}}
@use('App\Support\MailText')

@assets
<link href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/css/lightbox.min.css" rel="stylesheet" />
{{-- Bundle « plus-jquery » : Lightbox2 2.11 dépend de jQuery --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/js/lightbox-plus-jquery.min.js"></script>
<script>
    (function setupLightbox() {
        if (! window.lightbox) {
            return setTimeout(setupLightbox, 50);
        }

        window.lightbox.option({
            albumLabel: 'Image %1 sur %2',
            wrapAround: true,
            fadeDuration: 200,
            resizeDuration: 200,
            imageFadeDuration: 200,
            fitImagesInViewport: true,
        });

        // wire:navigate remplace le <body> : on reconstruit l'overlay s'il a disparu
        document.addEventListener('livewire:navigated', () => {
            if (! document.getElementById('lightbox')) {
                window.lightbox.build();
            }
        });
    })();
</script>
@endassets

<div class="flex min-h-0 min-w-0 flex-col overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-800/40">

    {{-- Barre d'actions : navigation à gauche, actions à droite --}}
    <div class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-zinc-200 bg-zinc-50 px-4 py-2.5 dark:border-white/10 dark:bg-white/5">
        <div class="flex items-center gap-2">
            @if ($selected)
                <div class="flex items-center gap-1">
                    <flux:button
                        size="xs"
                        variant="ghost"
                        wire:click="openMessage({{ $selectedMessageIndex - 1 }})"
                        :disabled="$selectedMessageIndex <= 0"
                        :aria-label="__('Message précédent')"
                    >
                        <i class="hgi-stroke hgi-arrow-up-01"></i>
                    </flux:button>

                    <flux:button
                        size="xs"
                        variant="ghost"
                        wire:click="openMessage({{ $selectedMessageIndex + 1 }})"
                        :disabled="$selectedMessageIndex >= count($messages) - 1"
                        :aria-label="__('Message suivant')"
                    >
                        <i class="hgi-stroke hgi-arrow-down-01"></i>
                    </flux:button>
                </div>

                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">
                    {{ __('Message') }} {{ $selectedMessageIndex + 1 }} / {{ count($messages) }}
                </span>
            @else
                <span class="flex items-center gap-2 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                    <i class="hgi-stroke hgi-mail-01"></i>
                    <span>{{ __('Aucun message sélectionné') }}</span>
                </span>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if (! empty($details['need_attention']))
                <flux:button size="sm" variant="danger" wire:click="$set('showReadModal', true)">
                    {{ __('Marquer comme lu') }}
                </flux:button>
            @endif

            <flux:button size="sm" variant="primary" wire:click="openCompose">
                <span class="flex items-center gap-2">
                    @if (count($messages) > 0)
                        <i class="hgi-stroke hgi-arrow-turn-backward"></i>
                        <span>{{ __('Répondre') }}</span>
                    @else
                        <i class="hgi-stroke hgi-sent"></i>
                        <span>{{ __('Nouveau message') }}</span>
                    @endif
                </span>
            </flux:button>
        </div>
    </div>

    @if ($selected)
        @php
            $meta       = $metas[$selectedMessageIndex];
            $files      = collect($selected['attachments'] ?? [])->map(fn ($a) => MailText::attachmentInfo($a));
            $imageFiles = $files->where('isImage', true)->filter(fn ($f) => $f['url']);
            $otherFiles = $files->where('isImage', false);
            $fromFull   = trim(MailText::decode($selected['from'] ?? ''));
            $toFull     = trim(MailText::decode($selected['to'] ?? ''));
        @endphp

        {{-- En-tête : objet, expéditeur, destinataire --}}
        <div class="shrink-0 space-y-4 border-b border-zinc-200 px-6 py-5 dark:border-white/10">
            <flux:heading size="lg" class="break-words leading-snug">{{ $meta['subject'] }}</flux:heading>

            <div class="flex items-start gap-3">
                <flux:avatar size="md" :name="$meta['name']" color="auto" :color:seed="$meta['name']" />

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="truncate font-medium text-zinc-900 dark:text-white">{{ $meta['name'] }}</span>

                        @if ($meta['role'] === 'client')
                            <flux:badge color="blue" size="sm">{{ __('Client') }}</flux:badge>
                        @elseif ($meta['role'] === 'support')
                            <flux:badge color="green" size="sm">{{ __('Support') }}</flux:badge>
                        @endif

                        @if ($files->count())
                            <flux:badge size="sm">
                                <span class="flex items-center gap-1">
                                    <i class="hgi-stroke hgi-attachment-01"></i>
                                    <span>{{ $files->count() }}</span>
                                </span>
                            </flux:badge>
                        @endif
                    </div>

                    <p class="mt-0.5 flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                        <i class="hgi-stroke hgi-calendar-03"></i>
                        <span>{{ $meta['date'] ?: '-' }}</span>
                    </p>
                </div>
            </div>

            <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1.5 rounded-lg bg-zinc-50 px-3.5 py-3 text-xs dark:bg-white/5">
                <dt class="font-medium text-zinc-500 dark:text-zinc-400">{{ __('De') }}</dt>
                <dd class="truncate text-zinc-700 dark:text-zinc-300" title="{{ $fromFull }}">{{ $fromFull ?: '-' }}</dd>

                <dt class="font-medium text-zinc-500 dark:text-zinc-400">{{ __('À') }}</dt>
                <dd class="truncate text-zinc-700 dark:text-zinc-300" title="{{ $toFull }}">{{ $toFull ?: '-' }}</dd>
            </dl>
        </div>

        {{-- Corps du message --}}
        <div class="min-h-0 flex-1 space-y-6 overflow-y-auto px-6 py-5">

            {{-- Étiquette + bouton traduire --}}
            <div class="flex items-center justify-between gap-3">
                @if ($translatedMessage)
                    <div class="inline-flex items-center gap-2 rounded-full bg-amber-100/80 px-3 py-1 text-xs font-medium text-amber-700 dark:bg-amber-400/15 dark:text-amber-300">
                        <i class="hgi-stroke hgi-translate"></i>
                        <span>{{ __('Version traduite') }}</span>
                    </div>

                    <flux:button size="xs" wire:click="showOriginalMessage">
                        <span class="flex items-center gap-1.5">
                            <i class="hgi-stroke hgi-arrow-turn-backward"></i>
                            <span>{{ __("Revenir à l'original") }}</span>
                        </span>
                    </flux:button>
                @else
                    <span class="text-[11px] font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500">{{ __('Message') }}</span>

                    <flux:button size="xs" wire:click="translateMessage" wire:loading.attr="disabled" wire:target="translateMessage">
                        <span class="flex items-center gap-1.5">
                            <i class="hgi-stroke hgi-translate" wire:loading.remove wire:target="translateMessage"></i>
                            <i class="hgi-stroke hgi-loading-03 animate-spin" wire:loading wire:target="translateMessage"></i>
                            <span>{{ __('Traduire en français') }}</span>
                        </span>
                    </flux:button>
                @endif
            </div>

            @if ($translatedMessage)
                <div class="break-words rounded-lg border border-amber-200/70 bg-amber-50/50 p-4 text-sm leading-relaxed text-zinc-800 dark:border-amber-400/20 dark:bg-amber-400/5 dark:text-zinc-200">
                    {!! $translatedMessage !!}
                </div>
            @else
                @php $parts = MailText::presentMessage((string) ($selected['message'] ?? '')); @endphp

                <div class="space-y-5">
                    {{-- Texte du message --}}
                    <div class="break-words text-sm leading-relaxed text-zinc-800 dark:text-zinc-200">
                        {!! $parts['body'] !!}
                    </div>

                    {{-- Signature --}}
                    @if ($parts['signature'])
                        <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-white/10 dark:bg-white/5">
                            <p class="mb-1.5 flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                                <i class="hgi-stroke hgi-signature"></i>
                                <span>{{ __('Signature') }}</span>
                            </p>
                            <div class="break-words text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">{!! $parts['signature'] !!}</div>
                        </div>
                    @endif

                    {{-- Historique cité (repliable) --}}
                    @if ($parts['quoted'])
                        <div wire:key="quoted-{{ $selectedMessageIndex }}" x-data="{ open: false }">
                            <button
                                type="button"
                                @click="open = ! open"
                                class="inline-flex items-center gap-1.5 rounded-md bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-600 transition hover:bg-zinc-200 dark:bg-white/10 dark:text-zinc-300 dark:hover:bg-white/15"
                            >
                                <i class="hgi-stroke hgi-more-horizontal"></i>
                                <span x-text="open ? 'Masquer l’historique du message' : 'Afficher l’historique du message'"></span>
                            </button>

                            <div x-show="open" x-collapse x-cloak class="mt-3 break-words border-l-2 border-sky-300 pl-3 text-xs leading-relaxed text-zinc-500 dark:border-sky-400/40 dark:text-zinc-400">
                                {!! $parts['quoted'] !!}
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Pièces jointes --}}
            @if ($files->count())
                <div class="space-y-3 border-t border-zinc-200 pt-5 dark:border-white/10">
                    <div class="flex items-center gap-2">
                        <i class="hgi-stroke hgi-attachment-01 text-zinc-500"></i>
                        <flux:heading>{{ __('Pièces jointes') }}</flux:heading>
                        <span class="rounded-full bg-zinc-200/70 px-2 py-0.5 text-xs font-medium text-zinc-600 dark:bg-white/10 dark:text-zinc-300">{{ $files->count() }}</span>
                    </div>

                    @if ($imageFiles->isNotEmpty())
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach ($imageFiles as $img)
                                {{-- Lightbox2 : un album par message (flèches ← → entre les images) --}}
                                <a
                                    href="{{ $img['url'] }}"
                                    data-lightbox="message-{{ $selectedMessageIndex }}"
                                    data-title="{{ $img['name'] }}"
                                    class="group relative block overflow-hidden rounded-lg shadow-[0_1px_3px_rgba(15,15,15,0.08)] ring-1 ring-black/[0.05] dark:ring-white/10"
                                >
                                    <img src="{{ $img['url'] }}" alt="{{ $img['name'] }}" loading="lazy" class="h-28 w-full object-cover transition duration-300 group-hover:scale-105" />
                                    <span class="absolute inset-x-0 bottom-0 truncate bg-gradient-to-t from-black/60 to-transparent px-2 pb-1 pt-4 text-xs text-white">{{ $img['name'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif

                    @if ($otherFiles->isNotEmpty())
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($otherFiles as $file)
                                <div class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-white px-3 py-2.5 dark:border-white/10 dark:bg-zinc-800">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-md bg-zinc-100 text-zinc-500 dark:bg-white/10 dark:text-zinc-300">
                                            <i class="hgi-stroke hgi-file-01" style="width:1.25rem;height:1.25rem;font-size:1.25rem"></i>
                                        </span>

                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-zinc-900 dark:text-white" title="{{ $file['name'] }}">{{ $file['name'] }}</p>
                                            <p class="text-xs uppercase text-zinc-500">
                                                {{ $file['ext'] }}@if ($file['size']) • {{ $file['size'] }}@endif
                                            </p>
                                        </div>
                                    </div>

                                    @if ($file['url'])
                                        <flux:button size="sm" variant="ghost" :href="$file['url']" target="_blank" download :aria-label="__('Télécharger')">
                                            <i class="hgi-stroke hgi-download-01"></i>
                                        </flux:button>
                                    @else
                                        <flux:badge size="sm">{{ __('Non disponible') }}</flux:badge>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @else
        <div class="flex flex-1 items-center justify-center p-8 text-center">
            <div class="space-y-3">
                <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-zinc-400/10">
                    <i class="hgi-stroke hgi-mail-01 text-zinc-400" style="width:1.75rem;height:1.75rem;font-size:1.75rem"></i>
                </div>
                <flux:heading>{{ __('Sélectionnez un message') }}</flux:heading>
                <flux:text>{{ __('Choisissez un message dans la liste pour afficher son contenu') }}</flux:text>
            </div>
        </div>
    @endif
</div>
