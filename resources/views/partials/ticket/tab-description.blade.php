{{-- Onglet « Ticket » (version compacte). Attend : $details, $messages --}}
@php
    $status = $this::STATUSES[$details['status'] ?? ''] ?? ['title' => ($details['status'] ?? '—'), 'color' => 'zinc'];
    $next   = $this->nextStatus;
    $todos  = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($details['to_do'] ?? '')))));

    $nextColor = $this::STATUSES[$next['next'] ?? '']['color'] ?? 'zinc';

    // Classes littérales (pour que Tailwind les détecte)
    $bg = [
        'amber' => 'bg-amber-500',
        'blue'  => 'bg-blue-500',
        'green' => 'bg-green-500',
        'zinc'  => 'bg-zinc-400',
    ];
    $text = [
        'amber' => 'text-amber-600 dark:text-amber-400',
        'blue'  => 'text-blue-600 dark:text-blue-400',
        'green' => 'text-green-600 dark:text-green-400',
        'zinc'  => 'text-zinc-700 dark:text-zinc-300',
    ];

    // ── Style de carte (seul endroit à modifier pour re-thémer toutes les cartes) ──
    // Conteneur gris très clair, coins arrondis
    $card      = 'overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50 dark:border-white/10 dark:bg-zinc-900';
    // En-tête : titre + description grise
    $cardHead  = 'px-4 py-3';
    $cardTitle = 'text-sm font-semibold text-zinc-900 dark:text-white';
    $cardDesc  = 'mt-0.5 text-xs text-zinc-500 dark:text-zinc-400';
    // Panneau blanc interne, en retrait du conteneur
    $panel     = 'mx-1 mb-1 overflow-hidden rounded-xl border border-zinc-300/70 bg-white dark:border-white/10 dark:bg-zinc-800';
    $divide    = 'divide-y divide-zinc-200/70 dark:divide-white/10';
    // Ligne libellé / valeur
    $row       = 'flex items-center justify-between gap-3 px-4 py-2.5';
    $label     = 'text-xs text-zinc-500 dark:text-zinc-400';
    $value     = 'min-w-0 truncate text-sm font-medium text-zinc-900 dark:text-white';

    // Progression
    $steps        = array_keys($this::STATUSES);
    $currentIndex = array_search($details['status'] ?? '', $steps, true);
    $currentIndex = $currentIndex === false ? -1 : $currentIndex;
    $activeColor  = $status['color'];

    // Client
    $clientName    = trim((string) ($details['nom_client'] ?? ''));
    $clientMail    = trim((string) ($details['original_client_mail'] ?? ''));
    $receptionMail = trim((string) ($details['reception_mail'] ?? ''));

    // Références copiables
    $refs = [
        ['label' => __('N° ticket'),   'value' => $details['num_ticket'] ?? null],
        ['label' => __('N° commande'), 'value' => $details['num_commande'] ?? null],
    ];

    // Dernier message (aperçu court)
    $lastMessage = ! empty($messages) ? end($messages) : null;
    $lastPreview = $lastMessage
        ? \Illuminate\Support\Str::limit(
            trim(preg_replace('/\s+/u', ' ', \App\Support\MailText::plainText((string) ($lastMessage['message'] ?? ''))) ?? ''),
            220
        )
        : '';
    $lastDate = $lastMessage['date'] ?? $lastMessage['created_at'] ?? null;
@endphp

<div class="space-y-4">

    {{-- ───────────── En-tête compact, dans une carte ───────────── --}}
    <div class="{{ $card }} p-1">
        <div class="flex flex-col gap-3 rounded-xl border border-zinc-300/70 bg-white px-4 py-3 lg:flex-row lg:items-center lg:justify-between dark:border-white/10 dark:bg-zinc-800">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-white px-2 py-0.5 text-xs font-medium text-zinc-700 dark:border-white/10 dark:bg-zinc-800 dark:text-zinc-200">
                        <span class="size-1.5 rounded-full {{ $bg[$status['color']] ?? $bg['zinc'] }}"></span>
                        {{ $status['title'] }}
                    </span>

                    @if (! empty($details['num_ticket']))
                        <span class="font-mono text-xs text-zinc-500 dark:text-zinc-400">#{{ $details['num_ticket'] }}</span>
                    @endif

                    @if (! empty($details['need_attention']))
                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">
                            {{ __('Le client a répondu') }}
                        </span>
                    @endif

                    <span class="text-xs text-zinc-500 dark:text-zinc-400">
                        @if ($clientName !== '') {{ $clientName }} · @endif
                        {{ count($messages) }} {{ __('message(s)') }}
                    </span>
                </div>

                <flux:heading size="lg" class="mt-1 break-words">
                    {{ $details['subject_ticket'] ?? __('Ticket sans objet') }}
                </flux:heading>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <flux:button variant="primary" icon="paper-airplane" wire:click="openCompose">
                    {{ __('Répondre') }}
                </flux:button>

                <flux:button
                    icon="pencil-square"
                    x-on:click="document.getElementById('note-card').scrollIntoView({ behavior: 'smooth', block: 'center' })"
                >
                    {{ __('Note') }}
                </flux:button>

                <flux:button
                    wire:click="updateStatus('{{ $next['next'] }}')"
                    wire:loading.attr="disabled"
                    wire:target="updateStatus"
                >
                    <span class="flex items-center gap-1.5">
                        <span class="size-2 rounded-full {{ $bg[$nextColor] ?? $bg['zinc'] }}"></span>
                        <span>{{ $next['label'] }}</span>
                    </span>
                </flux:button>
            </div>
        </div>
    </div>

    {{-- ───────────── Grille : infos (1/3) · travail (2/3) ───────────── --}}
    <div class="grid items-start gap-4 xl:grid-cols-3">

        {{-- ================= Colonne gauche : contact · progression ================= --}}
        <div class="min-w-0 space-y-4">

            {{-- Carte : Contact & références --}}
            <div class="{{ $card }}">
                <div class="{{ $cardHead }}">
                    <div class="{{ $cardTitle }}">{{ __('Contact & références') }}</div>
                    <div class="{{ $cardDesc }}">{{ __('Client à l\'origine du ticket et numéros utiles.') }}</div>
                </div>

                <dl class="{{ $panel }} {{ $divide }}">
                    <div class="{{ $row }}">
                        <dt class="{{ $label }}">{{ __('Client') }}</dt>
                        <dd class="{{ $value }}">{{ $clientName !== '' ? $clientName : '—' }}</dd>
                    </div>

                    <div class="{{ $row }}">
                        <dt class="{{ $label }}">{{ __('E-mail') }}</dt>
                        <dd class="min-w-0">
                            @if ($clientMail !== '')
                                <a href="mailto:{{ $clientMail }}" class="block truncate text-sm text-sky-600 hover:underline dark:text-sky-400" title="{{ $clientMail }}">{{ $clientMail }}</a>
                            @else
                                <span class="text-sm text-zinc-400">—</span>
                            @endif
                        </dd>
                    </div>

                    <div class="{{ $row }}">
                        <dt class="{{ $label }}">{{ __('Reçu sur') }}</dt>
                        <dd class="min-w-0">
                            @if ($receptionMail !== '')
                                <a href="mailto:{{ $receptionMail }}" class="block truncate text-sm font-medium text-zinc-700 hover:underline dark:text-zinc-300" title="{{ $receptionMail }}">{{ $receptionMail }}</a>
                            @else
                                <span class="text-sm text-zinc-400">—</span>
                            @endif
                        </dd>
                    </div>

                    @foreach ($refs as $ref)
                        <div class="{{ $row }}" x-data="{ copied: false }">
                            <dt class="{{ $label }}">{{ $ref['label'] }}</dt>
                            <dd class="flex min-w-0 items-center gap-1.5">
                                <span class="truncate font-mono text-sm font-medium {{ $ref['value'] ? 'text-zinc-900 dark:text-white' : 'text-zinc-400' }}">
                                    {{ $ref['value'] ?: '—' }}
                                </span>

                                @if ($ref['value'])
                                    <button
                                        type="button"
                                        class="flex size-6 shrink-0 items-center justify-center rounded-md text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-white/10 dark:hover:text-zinc-200"
                                        title="{{ __('Copier') }}"
                                        x-on:click="navigator.clipboard.writeText(@js((string) $ref['value'])); copied = true; setTimeout(() => copied = false, 1500)"
                                    >
                                        <i x-show="! copied" class="iconoir-copy text-xs"></i>
                                        <i x-show="copied" x-cloak class="iconoir-check text-xs text-green-600 dark:text-green-400"></i>
                                    </button>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            {{-- Carte : Progression --}}
            <div class="{{ $card }}">
                <div class="{{ $cardHead }}">
                    <div class="{{ $cardTitle }}">{{ __('Progression') }}</div>
                    <div class="{{ $cardDesc }}">{{ __('Étape actuelle du traitement.') }}</div>
                </div>

                <div class="{{ $panel }} px-4 py-3">
                    <ol class="flex items-start gap-2">
                        @foreach ($this::STATUSES as $key => $step)
                            @php
                                $done    = $loop->index < $currentIndex;
                                $current = $loop->index === $currentIndex;
                            @endphp
                            <li class="flex-1" @if ($current) aria-current="step" @endif>
                                <span @class([
                                    'block h-1.5 rounded-full',
                                    ($bg[$activeColor] ?? $bg['zinc']) => $done || $current,
                                    'bg-zinc-200 dark:bg-white/10' => ! $done && ! $current,
                                ])></span>
                                <span @class([
                                    'mt-1.5 block text-xs',
                                    'font-bold '.($text[$activeColor] ?? $text['zinc']) => $current,
                                    'font-medium text-zinc-700 dark:text-zinc-300' => $done,
                                    'text-zinc-400 dark:text-zinc-500' => ! $done && ! $current,
                                ])>{{ $step['title'] }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </div>

        {{-- ================= Colonne droite : résumé · dernier message · note ================= --}}
        <div class="min-w-0 space-y-4 xl:col-span-2">

            {{-- Résumé --}}
            <div class="{{ $card }}">
                <div class="flex items-center justify-between gap-3 {{ $cardHead }}">
                    <div class="min-w-0">
                        <div class="{{ $cardTitle }}">{{ __('Résumé') }}</div>
                        <div class="{{ $cardDesc }}">{{ __('Points à traiter pour ce ticket.') }}</div>
                    </div>

                    @if ($todos)
                        <span class="rounded-full bg-zinc-200/70 px-2 py-0.5 text-xs font-bold text-zinc-600 dark:bg-white/10 dark:text-zinc-300">
                            {{ count($todos) }}
                        </span>
                    @endif
                </div>

                <div class="{{ $panel }}">
                    @if ($todos)
                        <ul class="max-h-[420px] min-h-[260px] overflow-y-auto {{ $divide }}">
                            @foreach ($todos as $item)
                                <li class="flex gap-2 px-4 py-2 text-sm text-zinc-700 dark:text-zinc-300">
                                    <i class="iconoir-check-circle mt-0.5 shrink-0 text-zinc-400"></i>
                                    <span class="min-w-0 break-words">{{ $item }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="flex flex-col items-center gap-1 px-4 py-5 text-center">
                            <i class="iconoir-task-list text-2xl text-zinc-300 dark:text-zinc-600"></i>
                            <div class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Aucun résumé') }}</div>
                            <div class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Aucun résumé disponible.') }}</div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Dernier message --}}
            <div class="{{ $card }}">
                <div class="flex items-center justify-between gap-3 {{ $cardHead }}">
                    <div class="min-w-0">
                        <div class="{{ $cardTitle }}">{{ __('Dernier message') }}</div>
                        <div class="{{ $cardDesc }}">
                            {{ $lastDate ?: __('Aperçu du dernier échange avec le client.') }}
                        </div>
                    </div>

                    <flux:button size="xs" variant="ghost" icon-trailing="arrow-right" wire:click="setTab('conversation')">
                        {{ __('Tout voir') }}
                    </flux:button>
                </div>

                <div class="{{ $panel }}">
                    @if ($lastPreview !== '')
                        <p class="whitespace-pre-line break-words px-4 py-3 text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">{{ $lastPreview }}</p>
                    @else
                        <div class="flex flex-col items-center gap-1 px-4 py-5 text-center">
                            <i class="iconoir-chat-bubble text-2xl text-zinc-300 dark:text-zinc-600"></i>
                            <div class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Aucun message') }}</div>
                            <div class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Aucun message pour le moment.') }}</div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Note interne --}}
            <div
                id="note-card"
                wire:ignore
                class="{{ $card }}"
                x-data="{
                    value: @entangle('note'),
                    editor: null,
                    init() {
                        const dark = document.documentElement.classList.contains('dark');

                        this.editor = Jodit.make(this.$refs.editor, {
                            language: 'fr',
                            theme: dark ? 'dark' : 'default',
                            height: 200,
                            minHeight: 160,
                            placeholder: @js(__('Écrire une note…')),
                            toolbarAdaptive: true,
                            toolbarSticky: false,
                            showCharsCounter: false,
                            showWordsCounter: false,
                            showXPathInStatusbar: false,
                            askBeforePasteHTML: false,
                            defaultActionOnPaste: 'insert_clear_html',
                            buttons: [
                                'bold', 'italic', 'underline', '|',
                                'ul', 'ol', '|',
                                'link', 'eraser', '|',
                                'undo', 'redo', '|',
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
                <div class="{{ $cardHead }}">
                    <div class="{{ $cardTitle }}">{{ __('Note interne') }}</div>
                    <div class="{{ $cardDesc }}">{{ __('Visible uniquement par l\'équipe.') }}</div>
                </div>

                <div class="{{ $panel }}">
                    <div class="p-3">
                        <textarea x-ref="editor"></textarea>

                        @error('note')
                        <flux:text class="mt-2 text-sm text-red-500">{{ $message }}</flux:text>
                        @enderror
                    </div>

                    <div class="flex items-center justify-end border-t border-zinc-300/70 px-4 py-2.5 dark:border-white/10">
                        <flux:button
                            size="sm"
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
    </div>
</div>
