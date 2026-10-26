{{-- Onglet « Ticket ». Attend : $details, $messages --}}
@php
    $status = $this::STATUSES[$details['status'] ?? ''] ?? ['title' => ($details['status'] ?? '—'), 'color' => 'zinc'];
    $next   = $this->nextStatus;
    $todos  = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($details['to_do'] ?? '')))));

    $nextColor = $this::STATUSES[$next['next'] ?? '']['color'] ?? 'zinc';

    // Classes littérales (pour que Tailwind les détecte)
    $accentBar = [
        'amber' => 'bg-amber-500',
        'blue'  => 'bg-blue-500',
        'green' => 'bg-green-500',
        'zinc'  => 'bg-zinc-400',
    ];
    $dotSolid = [
        'amber' => 'bg-amber-500 border-amber-500',
        'blue'  => 'bg-blue-500 border-blue-500',
        'green' => 'bg-green-500 border-green-500',
        'zinc'  => 'bg-zinc-400 border-zinc-400',
    ];
    $dotHalo = [
        'amber' => 'ring-4 ring-amber-500/25',
        'blue'  => 'ring-4 ring-blue-500/25',
        'green' => 'ring-4 ring-green-500/25',
        'zinc'  => 'ring-4 ring-zinc-400/25',
    ];
    $textActive = [
        'amber' => 'text-amber-600 dark:text-amber-400',
        'blue'  => 'text-blue-600 dark:text-blue-400',
        'green' => 'text-green-600 dark:text-green-400',
        'zinc'  => 'text-zinc-700 dark:text-zinc-300',
    ];

    // Progression
    $steps        = array_keys($this::STATUSES);
    $stepCount    = count($steps);
    $currentIndex = array_search($details['status'] ?? '', $steps, true);
    $currentIndex = $currentIndex === false ? -1 : $currentIndex;
    $trackInset   = $stepCount > 0 ? 50 / $stepCount : 0;
    $progress     = ($stepCount > 1 && $currentIndex > 0) ? ($currentIndex / ($stepCount - 1)) * 100 : 0;
    $activeColor  = $status['color'];

    // Client
    $clientName = trim((string) ($details['nom_client'] ?? ''));
    $initials   = $clientName !== ''
        ? collect(preg_split('/\s+/u', $clientName))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('')
        : '?';

    $clientMail    = trim((string) ($details['original_client_mail'] ?? ''));
    $receptionMail = trim((string) ($details['reception_mail'] ?? ''));

    // Références copiables
    $refs = [
        ['label' => __('N° ticket'),      'value' => $details['num_ticket'] ?? null,   'icon' => 'iconoir-hashtag'],
        ['label' => __('N° de commande'), 'value' => $details['num_commande'] ?? null, 'icon' => 'iconoir-shopping-bag'],
    ];

    // Dernier message de la conversation (aperçu)
    $lastMessage = ! empty($messages) ? end($messages) : null;
    $lastPreview = $lastMessage
        ? \Illuminate\Support\Str::limit(
            trim(preg_replace('/\s+/u', ' ', \App\Support\MailText::plainText((string) ($lastMessage['message'] ?? ''))) ?? ''),
            420
        )
        : '';
    $lastDate = $lastMessage['date'] ?? $lastMessage['created_at'] ?? null;
@endphp

<div class="space-y-6">

    {{-- ───────────── En-tête : titre, statut, actions ───────────── --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="flex min-w-0 gap-4">
            <span class="hidden w-1 shrink-0 rounded-full sm:block {{ $accentBar[$status['color']] ?? $accentBar['zinc'] }}"></span>

            <div class="min-w-0 space-y-2">
                <flux:heading size="xl" class="break-words">
                    {{ $details['subject_ticket'] ?? __('Ticket sans objet') }}
                </flux:heading>

                <div class="flex flex-wrap items-center gap-2">
                    @if (! empty($details['num_ticket']))
                        <span class="font-mono text-sm font-semibold text-zinc-500 dark:text-zinc-400">#{{ $details['num_ticket'] }}</span>
                        <span class="text-zinc-300 dark:text-zinc-600">•</span>
                    @endif

                    <flux:badge :color="$status['color']" size="sm" class="font-bold">{{ $status['title'] }}</flux:badge>

                    @if (! empty($details['need_attention']))
                        <flux:badge color="amber" size="sm" class="font-bold">
                            <span class="flex items-center gap-1">
                                <i class="iconoir-warning-triangle"></i>
                                <span>{{ __('Attention') }}</span>
                            </span>
                        </flux:badge>
                    @endif

                    <flux:badge size="sm" color="zinc" icon="envelope">
                        {{ count($messages) }} {{ __('message(s)') }}
                    </flux:badge>
                </div>
            </div>
        </div>

        <div class="flex shrink-0 flex-wrap items-center gap-2">
            {{-- Action principale : répondre au client --}}
            <flux:button variant="primary" icon="paper-airplane" wire:click="openCompose">
                {{ __('Répondre') }}
            </flux:button>

            <flux:button
                icon="pencil-square"
                x-on:click="document.getElementById('note-card').scrollIntoView({ behavior: 'smooth', block: 'center' })"
            >
                {{ __('Ajouter note') }}
            </flux:button>

            <flux:button
                variant="primary"
                :color="$nextColor"
                wire:click="updateStatus('{{ $next['next'] }}')"
                wire:loading.attr="disabled"
                wire:target="updateStatus"
            >
                {{ $next['label'] }}
            </flux:button>
        </div>
    </div>

    {{-- ───────────── Bandeau : client · références · progression ───────────── --}}
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">

        {{-- Client --}}
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-white/10">
            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">{{ __('Client') }}</h3>

            <div class="flex items-center gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-violet-100 text-sm font-bold text-violet-700 dark:bg-violet-500/20 dark:text-violet-300">
                    {{ $initials }}
                </span>

                <div class="min-w-0">
                    <div class="break-words text-sm font-semibold {{ $clientName !== '' ? 'text-zinc-900 dark:text-white' : 'text-zinc-400 dark:text-zinc-500' }}">
                        {{ $clientName !== '' ? $clientName : '—' }}
                    </div>

                    @if ($clientMail !== '')
                        <a href="mailto:{{ $clientMail }}" class="block break-all text-xs text-sky-600 hover:underline dark:text-sky-400">{{ $clientMail }}</a>
                    @else
                        <span class="text-xs text-zinc-400 dark:text-zinc-500">—</span>
                    @endif
                </div>
            </div>

            @if ($receptionMail !== '')
                <div class="mt-3 flex items-center gap-2 border-t border-zinc-100 pt-3 text-xs text-zinc-500 dark:border-white/5 dark:text-zinc-400">
                    <i class="iconoir-mail shrink-0"></i>
                    <span class="shrink-0">{{ __('Reçu sur') }}</span>
                    <a href="mailto:{{ $receptionMail }}" class="min-w-0 truncate font-medium text-zinc-700 hover:underline dark:text-zinc-300" title="{{ $receptionMail }}">{{ $receptionMail }}</a>
                </div>
            @endif
        </div>

        {{-- Références (copiables) --}}
        <div class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-white/10">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">{{ __('Références') }}</h3>

            @foreach ($refs as $ref)
                <div class="flex items-center gap-3" x-data="{ copied: false }">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-white/10 dark:text-zinc-400">
                        <i class="{{ $ref['icon'] }}"></i>
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $ref['label'] }}</div>
                        <div class="truncate font-mono text-sm font-semibold {{ $ref['value'] ? 'text-zinc-900 dark:text-white' : 'text-zinc-400 dark:text-zinc-500' }}">
                            {{ $ref['value'] ?: '—' }}
                        </div>
                    </div>

                    @if ($ref['value'])
                        <button
                            type="button"
                            class="flex size-8 shrink-0 items-center justify-center rounded-lg text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-white/10 dark:hover:text-zinc-200"
                            title="{{ __('Copier') }}"
                            x-on:click="navigator.clipboard.writeText(@js((string) $ref['value'])); copied = true; setTimeout(() => copied = false, 1500)"
                        >
                            <i x-show="! copied" class="iconoir-copy"></i>
                            <i x-show="copied" x-cloak class="iconoir-check text-green-600 dark:text-green-400"></i>
                        </button>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Progression --}}
        <div class="rounded-xl border border-zinc-200 p-4 md:col-span-2 xl:col-span-1 dark:border-white/10">
            <h3 class="mb-5 text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">{{ __('Progression') }}</h3>

            <div class="relative">
                <div
                    class="absolute top-3 h-0.5 -translate-y-1/2 rounded-full bg-zinc-200 dark:bg-white/10"
                    style="left: {{ $trackInset }}%; right: {{ $trackInset }}%;"
                >
                    <div
                        class="h-full rounded-full transition-all duration-500 {{ $accentBar[$activeColor] ?? $accentBar['zinc'] }}"
                        style="width: {{ $progress }}%;"
                    ></div>
                </div>

                <ol class="relative flex items-start">
                    @foreach ($this::STATUSES as $key => $step)
                        @php
                            $index   = $loop->index;
                            $done    = $index < $currentIndex;
                            $current = $index === $currentIndex;
                            $isLast  = $loop->last;
                        @endphp

                        <li class="flex-1 text-center" @if ($current) aria-current="step" @endif>
                            <span @class([
                                'relative z-10 mx-auto flex size-6 items-center justify-center rounded-full border-2 text-xs transition-colors duration-300',
                                ($dotSolid[$activeColor] ?? $dotSolid['zinc']).' text-white' => $done || $current,
                                ($dotHalo[$activeColor] ?? $dotHalo['zinc']) => $current,
                                'border-zinc-300 bg-white dark:border-white/20 dark:bg-zinc-900' => ! $done && ! $current,
                            ])>
                                @if ($done || ($current && $isLast))
                                    <i class="iconoir-check"></i>
                                @elseif ($current)
                                    <span class="size-2 rounded-full bg-white"></span>
                                @endif
                            </span>

                            <span @class([
                                'mt-2 block text-xs',
                                'font-semibold '.($textActive[$activeColor] ?? $textActive['zinc']) => $current,
                                'font-medium text-zinc-700 dark:text-zinc-300' => $done,
                                'text-zinc-400 dark:text-zinc-500' => ! $done && ! $current,
                            ])>
                                {{ $step['title'] }}
                            </span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </div>

    {{-- ───────────── Zone de travail : contexte (gauche) · note (droite) ───────────── --}}
    <div class="grid items-start gap-6 xl:grid-cols-2">

        {{-- ===== Colonne gauche : dernier message + résumé ===== --}}
        <div class="space-y-6">

            {{-- Dernier message --}}
            <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-white/10">
                <div class="flex items-center justify-between gap-3 border-b border-zinc-200 px-4 py-3 sm:px-5 dark:border-white/10">
                    <div class="flex items-center gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400">
                            <i class="iconoir-chat-bubble text-lg"></i>
                        </span>
                        <div>
                            <flux:heading>{{ __('Dernier message') }}</flux:heading>
                            @if ($lastDate)
                                <flux:text class="text-xs">{{ $lastDate }}</flux:text>
                            @endif
                        </div>
                    </div>

                    <flux:button size="sm" variant="ghost" icon-trailing="arrow-right" wire:click="setTab('conversation')">
                        {{ __('Tout voir') }}
                    </flux:button>
                </div>

                <div class="p-4 sm:p-5">
                    @if ($lastPreview !== '')
                        <p class="whitespace-pre-line break-words text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">{{ $lastPreview }}</p>
                    @else
                        <div class="flex flex-col items-center gap-2 py-6 text-center">
                            <i class="iconoir-chat-bubble text-3xl text-zinc-300 dark:text-zinc-600"></i>
                            <flux:text>{{ __('Aucun message pour le moment.') }}</flux:text>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Résumé --}}
            <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-white/10">
                <div class="flex items-center justify-between gap-3 border-b border-zinc-200 px-4 py-3 sm:px-5 dark:border-white/10">
                    <div class="flex items-center gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-600 dark:bg-white/10 dark:text-zinc-300">
                            <i class="iconoir-task-list text-lg"></i>
                        </span>
                        <div>
                            <flux:heading>{{ __('Résumé') }}</flux:heading>
                            <flux:text class="text-xs">{{ __('Points à traiter pour ce ticket.') }}</flux:text>
                        </div>
                    </div>

                    @if ($todos)
                        <flux:badge size="sm" color="zinc">{{ count($todos) }}</flux:badge>
                    @endif
                </div>

                <div class="max-h-[360px] overflow-y-auto p-4 sm:p-5">
                    @if ($todos)
                        <ul class="space-y-2">
                            @foreach ($todos as $item)
                                <li class="flex gap-2.5 rounded-lg bg-zinc-50 px-3 py-2.5 text-sm text-zinc-700 dark:bg-white/5 dark:text-zinc-300">
                                    <i class="iconoir-check-circle mt-0.5 shrink-0 text-zinc-400"></i>
                                    <span class="min-w-0 break-words">{{ $item }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="flex flex-col items-center gap-2 py-6 text-center">
                            <i class="iconoir-task-list text-3xl text-zinc-300 dark:text-zinc-600"></i>
                            <flux:text>{{ __('Aucun résumé disponible.') }}</flux:text>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ===== Colonne droite : note (collée en haut au défilement) ===== --}}
        <div
            id="note-card"
            wire:ignore
            class="overflow-hidden rounded-xl border border-zinc-200 xl:sticky xl:top-6 dark:border-white/10"
            x-data="{
                value: @entangle('note'),
                editor: null,
                init() {
                    const dark = document.documentElement.classList.contains('dark');

                    this.editor = Jodit.make(this.$refs.editor, {
                        language: 'fr',
                        theme: dark ? 'dark' : 'default',
                        height: 320,
                        placeholder: @js(__('Écrire une note…')),
                        toolbarAdaptive: true,
                        toolbarSticky: false,
                        showCharsCounter: false,
                        showWordsCounter: false,
                        showXPathInStatusbar: false,
                        askBeforePasteHTML: false,
                        defaultActionOnPaste: 'insert_clear_html',
                        buttons: [
                            'bold', 'italic', 'underline', 'strikethrough', '|',
                            'ul', 'ol', '|',
                            'paragraph', 'fontsize', 'brush', '|',
                            'align', 'indent', 'outdent', '|',
                            'table', 'link', 'hr', '|',
                            'undo', 'redo', 'eraser', '|',
                            'source', 'fullsize',
                        ],
                    });

                    this.editor.value = this.value ?? '';

                    this.editor.events.on('change', (v) => {
                        if (v !== this.value) this.value = v;
                    });

                    this.$watch('value', (v) => {
                        if ((v ?? '') !== this.editor.value) this.editor.value = v ?? '';
                    });
                },
                destroy() {
                    this.editor?.destruct();
                },
            }"
        >
            <div class="flex items-center gap-3 border-b border-zinc-200 px-4 py-3 sm:px-5 dark:border-white/10">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-600 dark:bg-white/10 dark:text-zinc-300">
                    <i class="iconoir-notes text-lg"></i>
                </span>
                <div>
                    <flux:heading>{{ __('Note interne') }}</flux:heading>
                    <flux:text class="text-xs">{{ __('Visible uniquement par l\'équipe.') }}</flux:text>
                </div>
            </div>

            <div class="p-4 sm:p-5">
                <textarea x-ref="editor"></textarea>

                @error('note')
                <flux:text class="mt-2 text-sm text-red-500">{{ $message }}</flux:text>
                @enderror
            </div>

            <div class="flex items-center justify-end border-t border-zinc-200 bg-zinc-50 px-4 py-3 sm:px-5 dark:border-white/10 dark:bg-white/5">
                <flux:button
                    variant="outline"
                    color="green"
                    icon="check"
                    wire:click="addNote"
                    wire:loading.attr="disabled"
                    wire:target="addNote"
                >
                    {{ __('Enregistrer la note') }}
                </flux:button>
            </div>
        </div>
    </div>
</div>
