{{-- Onglet « Ticket ». Attend : $details --}}
@php
    $status = $this::STATUSES[$details['status'] ?? ''] ?? ['title' => ($details['status'] ?? '—'), 'color' => 'zinc'];
    $next   = $this->nextStatus;
    $todos  = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($details['to_do'] ?? '')))));

    // Couleur du bouton d'action = couleur du statut vers lequel on passe
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

    // Progression : étapes dans l'ordre de STATUSES
    $steps        = array_keys($this::STATUSES);
    $stepCount    = count($steps);
    $currentIndex = array_search($details['status'] ?? '', $steps, true);
    $currentIndex = $currentIndex === false ? -1 : $currentIndex;

    // Rail : du centre de la 1re pastille au centre de la dernière (chaque étape occupe 1/N de la largeur)
    $trackInset = $stepCount > 0 ? 50 / $stepCount : 0;
    $progress   = ($stepCount > 1 && $currentIndex > 0) ? ($currentIndex / ($stepCount - 1)) * 100 : 0;

    // Toute la progression prend la couleur du statut actuel
    $activeColor = $status['color'];

    // Initiales du client pour l'avatar
    $clientName = trim((string) ($details['nom_client'] ?? ''));
    $initials   = $clientName !== ''
        ? collect(preg_split('/\s+/u', $clientName))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('')
        : '?';

    $clientMail    = trim((string) ($details['original_client_mail'] ?? ''));
    $receptionMail = trim((string) ($details['reception_mail'] ?? ''));

    // Références copiables (icônes Iconoir)
    $refs = [
        ['label' => __('N° ticket'),       'value' => $details['num_ticket'] ?? null,   'icon' => 'iconoir-hashtag'],
        ['label' => __('N° de commande'),  'value' => $details['num_commande'] ?? null, 'icon' => 'iconoir-shopping-bag'],
    ];
@endphp

<div class="space-y-6">
    {{-- ───────────── En-tête : titre, statut, actions ───────────── --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex min-w-0 gap-4">
            {{-- Liseré de la couleur du statut --}}
            <span class="hidden w-1 shrink-0 rounded-full sm:block {{ $accentBar[$status['color']] ?? $accentBar['zinc'] }}"></span>

            <div class="min-w-0 space-y-2">
                <flux:heading size="xl" class="break-words">
                    {{ $details['subject_ticket'] ?? __('Ticket sans objet') }}
                </flux:heading>

                <div class="flex flex-wrap items-center gap-2">
                    @if (! empty($details['num_ticket']))
                        <span class="font-mono text-sm font-semibold text-zinc-500 dark:text-zinc-400">
                            #{{ $details['num_ticket'] }}
                        </span>
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
                </div>
            </div>
        </div>

        <div class="flex shrink-0 flex-wrap items-center gap-2">
            {{-- Action secondaire : fait défiler jusqu'à l'éditeur de note --}}
            <flux:button
                x-on:click="document.getElementById('note-card').scrollIntoView({ behavior: 'smooth', block: 'center' })"
            >
                {{ __('Ajouter note') }}
            </flux:button>

            {{-- Action principale : changement de statut --}}
            <flux:button
                variant="outline"
                :color="$nextColor"
                wire:click="updateStatus('{{ $next['next'] }}')"
                wire:loading.attr="disabled"
                wire:target="updateStatus"
            >
                {{ $next['label'] }}
            </flux:button>
        </div>
    </div>

    {{-- ───────────── Contenu : principal (2/3) + latéral (1/3) ───────────── --}}
    <div class="grid gap-6 lg:grid-cols-3">

        {{-- ===== Colonne principale : Résumé + Note ===== --}}
        <div class="space-y-6 lg:col-span-2">

            {{-- Résumé --}}
            <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-white/10">
                <div class="flex items-center justify-between gap-3 border-b border-zinc-200 px-4 py-4 sm:px-6 dark:border-white/10">
                    <div class="flex items-center gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-600 dark:bg-white/10 dark:text-zinc-300">
                            <i class="iconoir-task-list text-lg"></i>
                        </span>
                        <div>
                            <flux:heading>{{ __('Résumé') }}</flux:heading>
                            <flux:text class="text-sm">{{ __('Points à traiter pour ce ticket.') }}</flux:text>
                        </div>
                    </div>

                    @if ($todos)
                        <flux:badge size="sm" color="zinc">{{ count($todos) }}</flux:badge>
                    @endif
                </div>

                <div class="max-h-[500px] overflow-y-auto p-4 sm:p-6">
                    @if ($todos)
                        <ul class="space-y-2">
                            @foreach ($todos as $item)
                                <li class="rounded-lg bg-zinc-50 px-3 py-2.5 text-sm text-zinc-700 dark:bg-white/5 dark:text-zinc-300">
                                    <span class="block min-w-0 break-words">{{ $item }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="flex flex-col items-center gap-2 py-8 text-center">
                            <i class="iconoir-task-list text-3xl text-zinc-300 dark:text-zinc-600"></i>
                            <flux:text>{{ __('Aucun résumé disponible.') }}</flux:text>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Note : éditeur Jodit (HTML5, gratuit, MIT) --}}
            <div
                id="note-card"
                wire:ignore
                class="overflow-hidden rounded-xl border border-zinc-200 dark:border-white/10"
                x-data="{
                    value: @entangle('note'),
                    editor: null,
                    init() {
                        const dark = document.documentElement.classList.contains('dark');

                        this.editor = Jodit.make(this.$refs.editor, {
                            language: 'fr',
                            theme: dark ? 'dark' : 'default',
                            height: 340,
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

                        // Vide l'éditeur quand Livewire remet $note à ''
                        this.$watch('value', (v) => {
                            if ((v ?? '') !== this.editor.value) this.editor.value = v ?? '';
                        });
                    },
                    destroy() {
                        this.editor?.destruct();
                    },
                }"
            >
                <div class="flex items-center gap-3 border-b border-zinc-200 px-4 py-4 sm:px-6 dark:border-white/10">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-600 dark:bg-white/10 dark:text-zinc-300">
                        <i class="iconoir-notes text-lg"></i>
                    </span>
                    <div>
                        <flux:heading>{{ __('Note') }}</flux:heading>
                        <flux:text class="text-sm">{{ __('Ajoutez une note interne à ce ticket.') }}</flux:text>
                    </div>
                </div>

                <div class="p-4 sm:p-6">
                    <textarea x-ref="editor"></textarea>

                    @error('note')
                    <flux:text class="mt-2 text-sm text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>

                {{-- Pied de carte : aide + action --}}
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 bg-zinc-50 px-4 py-3 sm:px-6 dark:border-white/10 dark:bg-white/5">
                    <flux:text class="text-xs">{{ __('Visible uniquement par l\'équipe.') }}</flux:text>

                    <flux:button
                        variant="outline"
                        color="green"
                        wire:click="addNote"
                        wire:loading.attr="disabled"
                        wire:target="addNote"
                    >
                        {{ __('Enregistrer la note') }}
                    </flux:button>
                </div>
            </div>
        </div>

        {{-- ===== Colonne latérale (en premier sur mobile) ===== --}}
        <aside class="order-first space-y-6 lg:order-none lg:col-span-1">
            <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-white/10 lg:sticky lg:top-6">

                {{-- Progression du statut --}}
                <div class="border-b border-zinc-200 p-4 dark:border-white/10">
                    <h3 class="mb-5 text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                        {{ __('Progression') }}
                    </h3>

                    <div class="relative">
                        {{-- Rail (derrière les pastilles) : du centre de la 1re au centre de la dernière --}}
                        <div
                            class="absolute top-3 h-0.5 -translate-y-1/2 rounded-full bg-zinc-200 dark:bg-white/10"
                            style="left: {{ $trackInset }}%; right: {{ $trackInset }}%;"
                        >
                            {{-- Remplissage jusqu'à l'étape en cours --}}
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
                                    {{-- Pastille : passée = coche / en cours = halo + point (coche si dernière étape) / future = vide --}}
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

                {{-- Références (copiables) --}}
                <div class="space-y-3 border-b border-zinc-200 p-4 dark:border-white/10">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                        {{ __('Références') }}
                    </h3>

                    @foreach ($refs as $ref)
                        <div
                            class="flex items-center gap-3"
                            x-data="{ copied: false }"
                        >
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

                {{-- Client --}}
                <div class="p-4">
                    <h3 class="mb-4 text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                        {{ __('Client') }}
                    </h3>

                    <div class="flex items-center gap-3">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-violet-100 text-sm font-bold text-violet-700 dark:bg-violet-500/20 dark:text-violet-300">
                            {{ $initials }}
                        </span>

                        <div class="min-w-0">
                            <div class="break-words text-sm font-semibold {{ $clientName !== '' ? 'text-zinc-900 dark:text-white' : 'text-zinc-400 dark:text-zinc-500' }}">
                                {{ $clientName !== '' ? $clientName : '—' }}
                            </div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Nom du client') }}</div>
                        </div>
                    </div>

                    <dl class="mt-4 space-y-3">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400">
                                <i class="iconoir-mail"></i>
                            </span>
                            <div class="min-w-0">
                                <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('E-mail du client') }}</dt>
                                <dd class="break-words text-sm font-medium">
                                    @if ($clientMail !== '')
                                        <a href="mailto:{{ $clientMail }}" class="text-sky-600 hover:underline dark:text-sky-400">{{ $clientMail }}</a>
                                    @else
                                        <span class="text-zinc-400 dark:text-zinc-500">—</span>
                                    @endif
                                </dd>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-white/10 dark:text-zinc-400">
                                <i class="iconoir-mail"></i>
                            </span>
                            <div class="min-w-0">
                                <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('E-mail de réception') }}</dt>
                                <dd class="break-words text-sm font-medium">
                                    @if ($receptionMail !== '')
                                        <a href="mailto:{{ $receptionMail }}" class="text-zinc-700 hover:underline dark:text-zinc-300">{{ $receptionMail }}</a>
                                    @else
                                        <span class="text-zinc-400 dark:text-zinc-500">—</span>
                                    @endif
                                </dd>
                            </div>
                        </div>
                    </dl>
                </div>
            </div>
        </aside>
    </div>
</div>
