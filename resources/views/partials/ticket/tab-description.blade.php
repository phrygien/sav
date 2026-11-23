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

    // ── Style de carte (seul endroit à modifier pour re-thémer toutes les cartes) ──
    // Conteneur gris très clair, coins arrondis
    $card      = 'overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50 dark:border-white/10 dark:bg-zinc-900';
    // En-tête de carte : titre gras + description grise
    $cardHead  = 'px-6 py-5';
    $cardTitle = 'text-sm font-bold text-zinc-900 dark:text-white';
    $cardDesc  = 'mt-1 text-sm text-zinc-500 dark:text-zinc-400';
    // Panneau blanc interne, en retrait du conteneur
    $panel     = 'mx-1.5 mb-1.5 overflow-hidden rounded-xl border border-zinc-300/70 bg-white dark:border-white/10 dark:bg-zinc-800';
    // Ligne de réglage : libellé à gauche, valeur à droite
    $row       = 'flex flex-col gap-3 px-6 py-6 sm:flex-row sm:items-center sm:justify-between sm:gap-6';
    $rowTitle  = 'text-sm font-bold text-zinc-900 dark:text-white';
    $rowDesc   = 'mt-1 text-sm text-zinc-400 dark:text-zinc-500';
    $divide    = 'divide-y divide-zinc-300/70 dark:divide-white/10';

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

    {{-- ───────────── En-tête : statut, titre, actions ───────────── --}}
    <div class="flex flex-col gap-5 border-b border-zinc-200 pb-6 lg:flex-row lg:items-start lg:justify-between dark:border-white/10">
        <div class="min-w-0 space-y-3">

            {{-- Ligne méta : statut · n° ticket · attention --}}
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-white px-2.5 py-1 text-xs font-medium text-zinc-700 dark:border-white/10 dark:bg-zinc-800 dark:text-zinc-200">
                    <span class="size-1.5 rounded-full {{ $accentBar[$status['color']] ?? $accentBar['zinc'] }}"></span>
                    {{ $status['title'] }}
                </span>

                @if (! empty($details['num_ticket']))
                    <span class="font-mono text-xs font-medium text-zinc-500 dark:text-zinc-400">#{{ $details['num_ticket'] }}</span>
                @endif

                @if (! empty($details['need_attention']))
                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">
                        <i class="iconoir-warning-triangle"></i>
                        {{ __('Attention') }}
                    </span>
                @endif
            </div>

            {{-- Titre --}}
            <flux:heading size="xl" class="break-words">
                {{ $details['subject_ticket'] ?? __('Ticket sans objet') }}
            </flux:heading>

            {{-- Sous-titre : client · nombre de messages --}}
            <flux:text class="text-sm">
                @if ($clientName !== '')
                    {{ $clientName }} ·
                @endif
                {{ count($messages) }} {{ __('message(s)') }}
            </flux:text>
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

            {{-- Changement de statut : bouton neutre, pastille de la couleur du prochain statut --}}
            <flux:button
                wire:click="updateStatus('{{ $next['next'] }}')"
                wire:loading.attr="disabled"
                wire:target="updateStatus"
            >
                <span class="flex items-center gap-2">
                    <span class="size-2 rounded-full {{ $accentBar[$nextColor] ?? $accentBar['zinc'] }}"></span>
                    <span>{{ $next['label'] }}</span>
                </span>
            </flux:button>
        </div>
    </div>

    {{-- ───────────── Deux colonnes : infos (gauche) · travail (droite) ───────────── --}}
    <div class="grid items-start gap-6 xl:grid-cols-2">

        {{-- ================= Colonne gauche : contact · références · progression ================= --}}
        <div class="min-w-0 space-y-6">

            {{-- Carte : Contact (client + adresse de réception) --}}
            <div class="{{ $card }}">
                <div class="{{ $cardHead }}">
                    <div class="{{ $cardTitle }}">{{ __('Contact') }}</div>
                    <div class="{{ $cardDesc }}">{{ __('Client à l\'origine du ticket et adresse de réception.') }}</div>
                </div>

                <div class="{{ $panel }} {{ $divide }}">
                    {{-- Client --}}
                    <div class="{{ $row }}">
                        <div class="min-w-0">
                            <div class="{{ $rowTitle }}">{{ __('Client') }}</div>
                            <div class="{{ $rowDesc }}">{{ __('Contact à l\'origine du ticket.') }}</div>
                        </div>

                        <div class="min-w-0 sm:max-w-[55%]">
                            <div class="flex items-center gap-3">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-violet-100 text-sm font-bold text-violet-700 dark:bg-violet-500/20 dark:text-violet-300">
                                    {{ $initials }}
                                </span>

                                <div class="min-w-0">
                                    <div class="break-words text-sm font-bold {{ $clientName !== '' ? 'text-zinc-900 dark:text-white' : 'text-zinc-400 dark:text-zinc-500' }}">
                                        {{ $clientName !== '' ? $clientName : '—' }}
                                    </div>

                                    @if ($clientMail !== '')
                                        <a href="mailto:{{ $clientMail }}" class="block break-all text-xs text-sky-600 hover:underline dark:text-sky-400">{{ $clientMail }}</a>
                                    @else
                                        <span class="text-xs text-zinc-400 dark:text-zinc-500">—</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Reçu sur --}}
                    <div class="{{ $row }}">
                        <div class="min-w-0">
                            <div class="{{ $rowTitle }}">{{ __('Reçu sur') }}</div>
                            <div class="{{ $rowDesc }}">{{ __('Adresse de réception du support.') }}</div>
                        </div>

                        @if ($receptionMail !== '')
                            <a href="mailto:{{ $receptionMail }}" class="min-w-0 truncate text-sm font-semibold text-zinc-700 hover:underline dark:text-zinc-300" title="{{ $receptionMail }}">{{ $receptionMail }}</a>
                        @else
                            <span class="text-sm font-bold text-zinc-400 dark:text-zinc-500">—</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Carte : Références (copiables) --}}
            <div class="{{ $card }}">
                <div class="{{ $cardHead }}">
                    <div class="{{ $cardTitle }}">{{ __('Références') }}</div>
                    <div class="{{ $cardDesc }}">{{ __('Numéros utiles pour suivre ce ticket.') }}</div>
                </div>

                <div class="{{ $panel }} {{ $divide }}">
                    @foreach ($refs as $ref)
                        <div class="{{ $row }}" x-data="{ copied: false }">
                            <div class="min-w-0">
                                <div class="{{ $rowTitle }}">{{ $ref['label'] }}</div>
                                <div class="{{ $rowDesc }}">{{ __('Cliquer pour copier la référence.') }}</div>
                            </div>

                            <div class="flex min-w-0 items-center gap-2">
                                <span class="truncate font-mono text-sm font-bold {{ $ref['value'] ? 'text-zinc-900 dark:text-white' : 'text-zinc-400 dark:text-zinc-500' }}">
                                    {{ $ref['value'] ?: '—' }}
                                </span>

                                @if ($ref['value'])
                                    <button
                                        type="button"
                                        class="flex size-8 shrink-0 items-center justify-center rounded-lg border border-zinc-300/70 text-zinc-400 transition hover:bg-zinc-50 hover:text-zinc-700 dark:border-white/10 dark:hover:bg-white/10 dark:hover:text-zinc-200"
                                        title="{{ __('Copier') }}"
                                        x-on:click="navigator.clipboard.writeText(@js((string) $ref['value'])); copied = true; setTimeout(() => copied = false, 1500)"
                                    >
                                        <i x-show="! copied" class="{{ $ref['icon'] }}"></i>
                                        <i x-show="copied" x-cloak class="iconoir-check text-green-600 dark:text-green-400"></i>
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Carte : Progression --}}
            <div class="{{ $card }}">
                <div class="{{ $cardHead }}">
                    <div class="{{ $cardTitle }}">{{ __('Progression') }}</div>
                    <div class="{{ $cardDesc }}">{{ __('Étape actuelle du traitement.') }}</div>
                </div>

                <div class="{{ $panel }} px-6 py-8">
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
                                        'font-bold '.($textActive[$activeColor] ?? $textActive['zinc']) => $current,
                                        'font-semibold text-zinc-700 dark:text-zinc-300' => $done,
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
        </div>

        {{-- ================= Colonne droite : résumé · dernier message · note ================= --}}
        <div class="min-w-0 space-y-6">

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
                        <ul class="max-h-[360px] overflow-y-auto {{ $divide }}">
                            @foreach ($todos as $item)
                                <li class="flex gap-2.5 px-6 py-4 text-sm text-zinc-700 dark:text-zinc-300">
                                    <i class="iconoir-check-circle mt-0.5 shrink-0 text-zinc-400"></i>
                                    <span class="min-w-0 break-words">{{ $item }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="flex flex-col items-center gap-2 px-6 py-10 text-center">
                            <i class="iconoir-task-list text-3xl text-zinc-300 dark:text-zinc-600"></i>
                            <div class="{{ $rowTitle }}">{{ __('Aucun résumé') }}</div>
                            <div class="text-sm text-zinc-400 dark:text-zinc-500">{{ __('Aucun résumé disponible.') }}</div>
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

                    <flux:button size="sm" variant="ghost" icon-trailing="arrow-right" wire:click="setTab('conversation')">
                        {{ __('Tout voir') }}
                    </flux:button>
                </div>

                <div class="{{ $panel }}">
                    @if ($lastPreview !== '')
                        <p class="whitespace-pre-line break-words px-6 py-5 text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">{{ $lastPreview }}</p>
                    @else
                        <div class="flex flex-col items-center gap-2 px-6 py-10 text-center">
                            <i class="iconoir-chat-bubble text-3xl text-zinc-300 dark:text-zinc-600"></i>
                            <div class="{{ $rowTitle }}">{{ __('Aucun message') }}</div>
                            <div class="text-sm text-zinc-400 dark:text-zinc-500">{{ __('Aucun message pour le moment.') }}</div>
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
                <div class="{{ $cardHead }}">
                    <div class="{{ $cardTitle }}">{{ __('Note interne') }}</div>
                    <div class="{{ $cardDesc }}">{{ __('Visible uniquement par l\'équipe.') }}</div>
                </div>

                <div class="{{ $panel }}">
                    <div class="p-4 sm:p-5">
                        <textarea x-ref="editor"></textarea>

                        @error('note')
                        <flux:text class="mt-2 text-sm text-red-500">{{ $message }}</flux:text>
                        @enderror
                    </div>

                    <div class="flex items-center justify-end border-t border-zinc-300/70 px-6 py-4 dark:border-white/10">
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
    </div>
</div>
