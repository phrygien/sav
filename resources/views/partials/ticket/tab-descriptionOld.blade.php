{{-- Onglet « Ticket ». Attend : $details --}}
@php
    $status = $this::STATUSES[$details['status'] ?? ''] ?? ['title' => ($details['status'] ?? '—'), 'color' => 'zinc'];
    $next   = $this->nextStatus;
    $todos  = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($details['to_do'] ?? ''))));

    // Couleurs du texte du statut (classes littérales pour que Tailwind les détecte)
    $statusText = [
        'amber' => 'text-amber-600 dark:text-amber-400',
        'blue'  => 'text-blue-600 dark:text-blue-400',
        'green' => 'text-green-600 dark:text-green-400',
        'zinc'  => 'text-zinc-700 dark:text-zinc-300',
    ];

    // Champ : libellé, valeur, classes de couleur (appliquées seulement si la valeur existe)
    $fields = [
        [
            'label' => __('Statut'),
            'value' => $status['title'],
            'class' => $statusText[$status['color']] ?? $statusText['zinc'],
        ],
        [
            'label' => __('N° ticket'),
            'value' => $details['num_ticket'] ?? null,
            'class' => 'text-indigo-600 dark:text-indigo-400',
        ],
        [
            'label' => __('N° de commande'),
            'value' => $details['num_commande'] ?? null,
            'class' => 'text-emerald-600 dark:text-emerald-400',
        ],
        [
            'label' => __('Objet'),
            'value' => $details['subject_ticket'] ?? null,
            'class' => 'text-zinc-900 dark:text-white',
        ],
        [
            'label' => __('E-mail du client'),
            'value' => trim((string) ($details['original_client_mail'] ?? '')),
            'class' => 'text-sky-600 dark:text-sky-400',
        ],
        [
            'label' => __('E-mail de réception'),
            'value' => $details['reception_mail'] ?? null,
            'class' => 'text-zinc-700 dark:text-zinc-300',
        ],
        [
            'label' => __('Nom du client'),
            'value' => $details['nom_client'] ?? null,
            'class' => 'text-violet-600 dark:text-violet-400',
        ],
    ];
@endphp

<div class="space-y-6">
    {{-- Barre d'actions --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <flux:badge :color="$status['color']" size="sm" class="font-bold">{{ $status['title'] }}</flux:badge>

            @if (! empty($details['need_attention']))
                <flux:badge color="amber" size="sm" class="font-bold">
                    <span class="flex items-center gap-1">
                        <i class="hgi-stroke hgi-alert-02"></i>
                        <span>{{ __('Attention') }}</span>
                    </span>
                </flux:badge>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            {{-- Fait défiler jusqu'à l'éditeur de note --}}
            <flux:button
                size="sm"
                x-on:click="document.getElementById('note-card').scrollIntoView({ behavior: 'smooth', block: 'center' })"
            >
                {{ __('Ajouter note') }}
            </flux:button>

            <flux:button
                variant="primary"
                size="sm"
                wire:click="updateStatus('{{ $next['next'] }}')"
                wire:loading.attr="disabled"
                wire:target="updateStatus"
            >
                {{ $next['label'] }}
            </flux:button>
        </div>
    </div>

    {{-- Description du ticket : pleine largeur, 3 colonnes --}}
    <div class="w-full rounded-lg border border-zinc-200 dark:border-white/10">
        <div class="border-b border-zinc-200 px-4 py-4 dark:border-white/10">
            <flux:heading>{{ __('Infos ticket') }}</flux:heading>
            <flux:text class="mt-1 text-sm">{{ __('Toutes les informations sur le ticket.') }}</flux:text>
        </div>

        <dl class="grid grid-cols-1 gap-x-6 gap-y-5 p-4 sm:grid-cols-3">
            @foreach ($fields as $field)
                <div class="min-w-0">
                    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        {{ $field['label'] }}
                    </dt>
                    <dd class="mt-1 break-words text-sm {{ $field['value'] ? 'font-bold '.$field['class'] : 'text-zinc-400 dark:text-zinc-500' }}">
                        {{ $field['value'] ?: '—' }}
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>

    {{-- Cards du bas : Résumé + Note (TinyMCE) --}}
    <div class="grid gap-6 md:grid-cols-2">
        {{-- Résumé --}}
        <div class="rounded-lg bg-zinc-50 p-4 sm:p-6 dark:bg-zinc-900">
            <flux:heading>{{ __('Résumé') }}</flux:heading>

            <div class="mt-3 max-h-[500px] overflow-y-auto pr-2 text-sm text-zinc-700 dark:text-zinc-300">
                @if ($todos)
                    <ul class="space-y-2">
                        @foreach ($todos as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @else
                    <flux:text>{{ __('Aucun résumé disponible.') }}</flux:text>
                @endif
            </div>
        </div>

        {{-- Note : éditeur Jodit (HTML5, gratuit, MIT) --}}
        <div
            id="note-card"
            wire:ignore
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
            <flux:heading>{{ __('Note') }}</flux:heading>

            <div class="mt-3">
                <textarea x-ref="editor"></textarea>
            </div>

            @error('note')
            <flux:text class="mt-2 text-sm text-red-500">{{ $message }}</flux:text>
            @enderror

            <div class="mt-4 flex justify-end">
                <flux:button
                    variant="primary"
                    size="sm"
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
