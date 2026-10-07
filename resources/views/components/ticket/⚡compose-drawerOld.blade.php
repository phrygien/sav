<?php

use App\Livewire\Concerns\NotifiesWithToast;
use App\Services\CosmiaApi;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Tiroir « Répondre à l'email » : rédaction, CC, pièces jointes (FilePond), aide IA, envoi.
 * Ouvert par l'événement `compose-open` émis par la page ticket.
 * Après envoi, émet `ticket-updated` pour que la page recharge le ticket.
 */
new class extends Component
{
    use NotifiesWithToast;
    use WithFileUploads;

    #[Locked]
    public int $ticketId = 0;

    /** Contexte fourni par la page à l'ouverture (ticket, client, 1er message…) */
    #[Locked]
    public array $context = [];

    public bool $showComposeDrawer = false;

    public bool $showSendModal = false;

    public string $destinateur = '';

    public string $destinateurOriginal = '';

    public bool $editDestinataire = false;

    public array $cc = [];

    public string $subject = '';

    public string $subjectOriginal = '';

    public bool $editSubject = false;

    public string $message_client = '';

    public string $message_txt = '';

    // Pièces jointes validées, indexées par nom temporaire Livewire
    public $photos = [];

    // Fichier en cours de téléversement (FilePond), déplacé dans $photos une fois validé
    public $pendingFile = null;

    public function mount(int $ticketId): void
    {
        $this->ticketId = $ticketId;
    }

    #[On('compose-open')]
    public function open(array $context): void
    {
        if ((int) ($context['ticket_id'] ?? 0) !== $this->ticketId) {
            return;
        }

        $this->context        = $context;
        $this->message_client = (string) ($context['client_message'] ?? '');

        // On n'écrase pas un brouillon en cours (adresse / objet déjà initialisés)
        if ($this->destinateurOriginal === '') {
            $this->destinateur         = (string) ($context['client_mail'] ?? '');
            $this->destinateurOriginal = $this->destinateur;
        }

        if ($this->subjectOriginal === '') {
            $this->subject         = (string) ($context['subject'] ?? '');
            $this->subjectOriginal = $this->subject;
        }

        $this->showComposeDrawer = true;
    }

    // Fermeture du drawer côté client (croix, Échap, clic extérieur) : on vide les pièces jointes
    public function updatedShowComposeDrawer(bool $open): void
    {
        if (! $open) {
            $this->photos      = [];
            $this->pendingFile = null;
            $this->dispatch('attachments-cleared');
        }
    }

    /* ---------------------------- Champs ---------------------------- */

    public function resetDestinataire(): void
    {
        $this->destinateur      = $this->destinateurOriginal;
        $this->editDestinataire = false;
    }

    public function resetSubject(): void
    {
        $this->subject     = $this->subjectOriginal;
        $this->editSubject = false;
    }

    public function addCC(string $email): void
    {
        $email = trim($email);

        if ($email === '') {
            return;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->notify(__('Adresse email invalide : :email', ['email' => $email]), 'danger');

            return;
        }

        if (in_array($email, $this->cc, true)) {
            $this->notify(__('Cette adresse est déjà dans la liste CC'), 'warning');

            return;
        }

        $this->cc[] = $email;
    }

    public function removeCC(int $index): void
    {
        if (isset($this->cc[$index])) {
            array_splice($this->cc, $index, 1);
        }
    }

    /* ------------------------ Pièces jointes ------------------------ */

    // Appelé par FilePond via $wire.upload('pendingFile', ...)
    public function updatedPendingFile(): void
    {
        try {
            $this->validate(
                ['pendingFile' => ['file', 'max:5120']], // 5 Mo max
                ['pendingFile.max' => __('Chaque fichier doit faire 5 Mo maximum.')]
            );
        } catch (ValidationException $e) {
            $this->pendingFile = null;
            $this->notify(collect($e->errors())->flatten()->first(), 'danger');

            return;
        }

        $this->photos[$this->pendingFile->getFilename()] = $this->pendingFile;
        $this->pendingFile = null;
    }

    // Appelé par FilePond (server.revert) quand l'utilisateur retire un fichier
    public function removeAttachment(string $id): void
    {
        unset($this->photos[$id]);
    }

    /* ----------------------------- IA ------------------------------- */

    public function translateOpenAI(): void
    {
        if (trim($this->message_txt) === '') {
            $this->notify(__('Veuillez écrire un message avant de le traduire'), 'danger');

            return;
        }

        if (trim($this->message_client) === '') {
            $this->notify(__('Message client introuvable'), 'danger');

            return;
        }

        try {
            $api = app(CosmiaApi::class);

            $current = $api->post('/openai/detectlanguageiso', ['text' => $this->message_txt])['langue'] ?? null;

            if (! $current) {
                $this->notify(__('Impossible de détecter la langue'), 'danger');

                return;
            }

            $target = $api->post('/openai/detectlanguageiso', ['text' => $this->message_client])['langue'] ?? 'en';

            if ($current === $target) {
                $this->notify(__('Le message est déjà dans la langue du client'), 'info');

                return;
            }

            $translated = $api->post('/openai/translateandcorrect', [
                'text'   => $this->message_txt,
                'target' => $target,
            ])['translated_text'] ?? null;
        } catch (\RuntimeException $e) {
            $this->notify(__('Erreur lors de la traduction : ').$e->getMessage(), 'danger');

            return;
        }

        if (empty($translated)) {
            $this->notify(__('La traduction a échoué'), 'danger');

            return;
        }

        $this->message_txt = $translated;
        $this->notify(__('Message traduit de :from vers :to', ['from' => $current, 'to' => $target]));
    }

    public function correctionOpenAI(): void
    {
        if (trim($this->message_txt) === '') {
            $this->notify(__('Veuillez écrire un message avant de chercher une suggestion'), 'danger');

            return;
        }

        try {
            $corrected = app(CosmiaApi::class)->post('/openai/correct', ['text' => $this->message_txt])['corrected_text'] ?? null;
        } catch (\RuntimeException $e) {
            $this->notify(__('Erreur lors de la correction : ').$e->getMessage(), 'danger');

            return;
        }

        if (empty($corrected)) {
            $this->notify(__('La correction a échoué'), 'danger');

            return;
        }

        $this->message_txt = $corrected;
        $this->notify(__('Suggestion de texte appliquée'));
    }

    /* ----------------------------- Envoi ---------------------------- */

    public function reply(): void
    {
        $validator = Validator::make(
            [
                'message_txt' => $this->message_txt,
                'destinateur' => $this->destinateur,
                'subject'     => $this->subject,
            ],
            [
                'message_txt' => 'required|string',
                'destinateur' => 'required',
                'subject'     => 'required',
            ],
            [
                'message_txt.required' => __('Le message est obligatoire'),
                'destinateur.required' => __('Le destinataire est obligatoire'),
                'subject.required'     => __("L'objet du mail est obligatoire"),
            ]
        );

        if ($validator->fails()) {
            $this->showSendModal = false;

            foreach ($validator->errors()->all() as $message) {
                $this->notify($message, 'danger');
            }

            return;
        }

        $attachments = $this->buildAttachments();

        if (! empty($this->photos) && empty($attachments)) {
            $this->showSendModal = false;
            $this->notify(__("Aucun fichier valide n'a pu être traité"), 'danger');

            return;
        }

        $body = [
            'ticket_id'    => $this->ticketId,
            'replyText'    => $this->message_txt,
            'attachements' => $attachments,
            'destinataire' => $this->destinateur,
            'cc'           => $this->cc,
            'subject'      => $this->subject,
        ];

        // Réponse à un fil existant, ou nouveau mail si la conversation est vide
        if ((int) ($this->context['messages_count'] ?? 0) > 0) {
            $body['first_message_id'] = $this->context['first_message_id'] ?? null;
            $endpoint = '/ticket/replymail2';
        } else {
            $endpoint = '/ticket/sendNewMail';
        }

        try {
            app(CosmiaApi::class)->post($endpoint, $body);
        } catch (\RuntimeException $e) {
            $this->showSendModal = false;
            $this->notify(__("Erreur lors de l'envoi de l'email : ").$e->getMessage(), 'danger');

            return;
        }

        $sentTo      = $this->destinateur;
        $attachCount = count($attachments);

        $this->showSendModal     = false;
        $this->showComposeDrawer = false;
        $this->message_txt       = '';
        $this->photos            = [];
        $this->cc                = [];
        $this->editDestinataire  = false;
        $this->editSubject       = false;
        // Vidés pour que la prochaine ouverture reparte du contexte à jour
        $this->destinateurOriginal = '';
        $this->subjectOriginal     = '';

        $this->dispatch('attachments-cleared');
        $this->dispatch('ticket-updated');

        // Le rendu sombre/clair inversé vient de <flux:toast invert /> dans le layout
        Flux::toast(
            heading: __('Email envoyé'),
            text: $attachCount > 0
                ? __('Votre réponse a été envoyée à :email avec :count pièce(s) jointe(s).', ['email' => $sentTo, 'count' => $attachCount])
                : __('Votre réponse a été envoyée à :email.', ['email' => $sentTo]),
            variant: 'success',
            duration: 6000,
        );
    }

    private function buildAttachments(): array
    {
        $attachments = [];

        foreach ($this->photos as $file) {
            $content = $file->isValid() ? $file->get() : '';

            if ($content === '' || $content === false) {
                $this->notify(__('Le fichier :name est invalide ou vide', ['name' => $file->getClientOriginalName()]), 'danger');

                continue;
            }

            $attachments[] = [
                'filename'      => $file->getClientOriginalName(),
                'mimeType'      => $file->getMimeType(),
                'contentBase64' => base64_encode($content),
            ];
        }

        return $attachments;
    }
};
?>
<div>
    @assets
    <link href="https://unpkg.com/filepond@^4/dist/filepond.min.css" rel="stylesheet" />
    <style>
        .filepond--root { font-family: inherit; font-size: .875rem; margin-bottom: 0; }
        .filepond--panel-root { background-color: rgb(161 161 170 / .12); border: 1px dashed rgb(161 161 170 / .5); }
        .filepond--drop-label { color: rgb(113 113 122); }
        .dark .filepond--drop-label { color: rgb(161 161 170); }
        .filepond--label-action { text-decoration-color: rgb(99 102 241); color: rgb(79 70 229); }
        .filepond--item-panel { background-color: rgb(99 102 241); }
        [data-filepond-item-state='processing-complete'] .filepond--item-panel { background-color: rgb(16 185 129); }
        [data-filepond-item-state*='error'] .filepond--item-panel { background-color: rgb(239 68 68); }
    </style>
    <script src="https://unpkg.com/filepond@^4/dist/filepond.min.js"></script>
    <script src="https://unpkg.com/filepond-plugin-file-validate-size@^2/dist/filepond-plugin-file-validate-size.min.js"></script>
    <script src="https://unpkg.com/filepond-plugin-file-validate-type@^1/dist/filepond-plugin-file-validate-type.min.js"></script>
    @endassets

    @php
        $fieldInput = 'min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-zinc-900 outline-none placeholder:text-zinc-400 focus:ring-0 read-only:text-zinc-600 dark:text-white dark:read-only:text-zinc-400';
        $fieldLabel = 'w-14 shrink-0 text-sm font-medium text-zinc-500 dark:text-zinc-400';
        $fieldRow   = 'flex items-center gap-3 border-b border-zinc-200 px-4 py-3 dark:border-white/10';
        $linkBtn    = 'text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400';
    @endphp

    {{-- ========== Drawer extra-large : envoi mail ========== --}}
    <flux:modal wire:model.self="showComposeDrawer" variant="flyout" position="right" class="w-full max-w-full p-0 md:w-[64rem]">
        <div class="flex h-full flex-col">

            {{-- En-tête --}}
            <div class="shrink-0 border-b border-zinc-200 px-6 py-5 pe-14 dark:border-white/10">
                <flux:heading size="lg">{{ __("Répondre à l'email") }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('Ticket') }} {{ $context['num_ticket'] ?? $ticketId }}
                    @if (! empty($context['client_name'])) · {{ $context['client_name'] }} @endif
                    · {{ (int) ($context['messages_count'] ?? 0) }} {{ __('message(s)') }}
                </flux:text>
            </div>

            {{-- Corps défilant --}}
            <div class="min-h-0 flex-1 overflow-y-auto">

                {{-- À --}}
                <div class="{{ $fieldRow }}">
                    <span class="{{ $fieldLabel }}">{{ __('À') }}</span>

                    <input
                        type="email"
                        wire:model="destinateur"
                        placeholder="exemple@domaine.com"
                        @if (! $editDestinataire) readonly @endif
                        class="{{ $fieldInput }}"
                    />

                    <div class="flex shrink-0 items-center gap-3">
                        @if (! $editDestinataire)
                            <button type="button" wire:click="$set('editDestinataire', true)" class="{{ $linkBtn }}">{{ __('Modifier') }}</button>
                        @else
                            <button type="button" wire:click="$set('editDestinataire', false)" class="{{ $linkBtn }}">{{ __('Valider') }}</button>
                            <button type="button" wire:click="resetDestinataire" class="text-xs font-medium text-zinc-500 hover:underline">{{ __('Annuler') }}</button>
                        @endif
                    </div>
                </div>

                {{-- Cc --}}
                <div x-data="{ val: '' }" class="{{ $fieldRow }} items-start">
                    <span class="{{ $fieldLabel }} pt-1">{{ __('Cc') }}</span>

                    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                        @foreach ($cc as $index => $email)
                            <span wire:key="cc-{{ $index }}-{{ $email }}" class="inline-flex items-center gap-1 rounded-full bg-indigo-100/80 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-400/15 dark:text-indigo-300">
                                {{ $email }}
                                <button type="button" wire:click="removeCC({{ $index }})" class="flex items-center rounded-full p-0.5 hover:bg-indigo-200 dark:hover:bg-indigo-500/30" aria-label="{{ __('Retirer') }}">
                                    <i class="hgi-stroke hgi-cancel-01" style="width:.75rem;height:.75rem;font-size:.75rem"></i>
                                </button>
                            </span>
                        @endforeach

                        <input
                            type="email"
                            x-model="val"
                            @blur="if (val.trim() !== '') { $wire.addCC(val.trim()); val = '' }"
                            @keydown.enter.prevent="if (val.trim() !== '') { $wire.addCC(val.trim()); val = '' }"
                            placeholder="{{ __('Ajouter une adresse en copie…') }}"
                            class="min-w-[180px] flex-1 border-0 bg-transparent p-1 text-sm text-zinc-900 outline-none placeholder:text-zinc-400 focus:ring-0 dark:text-white"
                        />
                    </div>
                </div>

                {{-- Objet --}}
                <div class="{{ $fieldRow }}">
                    <span class="{{ $fieldLabel }}">{{ __('Objet') }}</span>

                    <input
                        type="text"
                        wire:model="subject"
                        @if (! $editSubject) readonly @endif
                        class="{{ $fieldInput }} font-medium"
                    />

                    <div class="flex shrink-0 items-center gap-3">
                        @if (! $editSubject)
                            <button type="button" wire:click="$set('editSubject', true)" class="{{ $linkBtn }}">{{ __('Modifier') }}</button>
                        @else
                            <button type="button" wire:click="$set('editSubject', false)" class="{{ $linkBtn }}">{{ __('Valider') }}</button>
                            <button type="button" wire:click="resetSubject" class="text-xs font-medium text-zinc-500 hover:underline">{{ __('Annuler') }}</button>
                        @endif
                    </div>
                </div>

                {{-- Message original du client (repliable) --}}
                @if ($message_client !== '')
                    <div x-data="{ open: false }" class="border-b border-zinc-200 dark:border-white/10">
                        <button
                            type="button"
                            @click="open = ! open"
                            class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-xs font-medium text-zinc-500 transition hover:bg-zinc-50 dark:text-zinc-400 dark:hover:bg-white/5"
                        >
                            <i class="hgi-stroke hgi-message-01"></i>
                            <span>{{ __('Message original du client') }}</span>
                            <span class="ml-auto flex items-center transition" :class="open && 'rotate-180'">
                                <i class="hgi-stroke hgi-arrow-down-01"></i>
                            </span>
                        </button>

                        <div x-show="open" x-collapse x-cloak>
                            <div class="mx-4 mb-3 max-h-40 overflow-y-auto whitespace-pre-line break-words border-l-2 border-sky-400 pl-3 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ \Illuminate\Support\Str::limit($message_client, 600) }}
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Éditeur --}}
                <textarea
                    wire:model="message_txt"
                    rows="16"
                    placeholder="{{ __('Rédigez votre réponse au client…') }}"
                    class="block w-full resize-y border-0 bg-transparent px-4 py-4 text-sm leading-relaxed text-zinc-900 outline-none placeholder:text-zinc-400 focus:ring-0 dark:text-white"
                ></textarea>

                {{-- Pièces jointes (FilePond) --}}
                <div
                    wire:ignore
                    x-data="{
                        pond: null,
                        async init() {
                            const wire = this.$wire;

                            while (! (window.FilePond && window.FilePondPluginFileValidateSize && window.FilePondPluginFileValidateType)) {
                                await new Promise(resolve => setTimeout(resolve, 50));
                            }

                            FilePond.registerPlugin(FilePondPluginFileValidateSize, FilePondPluginFileValidateType);

                            this.pond = FilePond.create(this.$refs.input, {
                                allowMultiple: true,
                                maxParallelUploads: 1,
                                maxFileSize: '5MB',
                                credits: false,
                                acceptedFileTypes: ['image/*', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                                labelIdle: 'Glissez vos fichiers ici ou <span class=\'filepond--label-action\'>parcourir</span>',
                                labelFileProcessing: 'Téléversement',
                                labelFileProcessingComplete: 'Téléversé',
                                labelFileProcessingAborted: 'Annulé',
                                labelFileProcessingError: 'Erreur de téléversement',
                                labelFileLoading: 'Chargement',
                                labelTapToCancel: 'cliquer pour annuler',
                                labelTapToRetry: 'cliquer pour réessayer',
                                labelTapToUndo: 'cliquer pour retirer',
                                labelButtonRemoveItem: 'Retirer',
                                labelButtonAbortItemLoad: 'Annuler',
                                labelButtonAbortItemProcessing: 'Annuler',
                                labelButtonRetryItemProcessing: 'Réessayer',
                                labelButtonProcessItem: 'Téléverser',
                                labelFileTypeNotAllowed: 'Type de fichier non autorisé',
                                fileValidateTypeLabelExpectedTypes: 'Images, PDF ou Word',
                                labelMaxFileSizeExceeded: 'Fichier trop volumineux',
                                labelMaxFileSize: 'Taille max : {filesize}',
                                server: {
                                    process: (fieldName, file, metadata, load, error, progress, abort) => {
                                        wire.upload(
                                            'pendingFile',
                                            file,
                                            (uploadedFilename) => load(uploadedFilename),
                                            () => error('Échec du téléversement'),
                                            (event) => progress(true, event.detail.progress, 100),
                                        );

                                        return {
                                            abort: () => {
                                                wire.cancelUpload('pendingFile');
                                                abort();
                                            },
                                        };
                                    },
                                    revert: (uniqueFileId, load, error) => {
                                        wire.removeAttachment(uniqueFileId).then(load).catch(() => error('Suppression impossible'));
                                    },
                                },
                            });
                        },
                        destroy() {
                            this.pond?.destroy();
                        },
                    }"
                    @attachments-cleared.window="pond?.removeFiles()"
                    class="border-t border-zinc-200 bg-zinc-50 px-4 py-4 dark:border-white/10 dark:bg-white/5"
                >
                    <input type="file" x-ref="input" multiple />

                    <p class="mt-2 text-xs text-zinc-400">{{ __('Images, PDF, Word — 5 Mo max par fichier') }}</p>
                </div>
            </div>

            {{-- Pied fixe : actions --}}
            <div class="flex shrink-0 flex-wrap items-center gap-2 border-t border-zinc-200 bg-white px-6 py-4 dark:border-white/10 dark:bg-zinc-800">
                <flux:modal.close>
                    <flux:button variant="danger" size="sm">
                        <span class="flex items-center gap-2">
                            <span>{{ __('Annuler') }}</span>
                        </span>
                    </flux:button>
                </flux:modal.close>

                <flux:spacer />

                <flux:button size="sm" wire:click="correctionOpenAI" wire:loading.attr="disabled" wire:target="correctionOpenAI">
                    <span class="flex items-center gap-2">
                        <i class="hgi-stroke hgi-magic-wand-01"></i>
                        <span>{{ __('Suggestion') }}</span>
                    </span>
                </flux:button>

                <flux:button size="sm" wire:click="translateOpenAI" wire:loading.attr="disabled" wire:target="translateOpenAI">
                    <span class="flex items-center gap-2">
                        <i class="hgi-stroke hgi-translate"></i>
                        <span>{{ __('Traduire') }}</span>
                    </span>
                </flux:button>

                <flux:button size="sm" variant="primary" wire:click="$set('showSendModal', true)">
                    <span class="flex items-center gap-2">
                        <i class="hgi-stroke hgi-sent"></i>
                        <span>{{ __('Envoyer la réponse') }}</span>
                    </span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modale : confirmer l'envoi (après le drawer dans le DOM pour s'afficher par-dessus) --}}
    <flux:modal wire:model.self="showSendModal" class="md:w-[28rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __("Confirmer l'envoi") }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __("Êtes-vous sûr de vouloir envoyer cette réponse ? L'email sera envoyé à") }}
                    <strong>{{ $destinateur }}</strong>@if (! empty($photos)), {{ __('avec') }} <strong>{{ count($photos) }} {{ __('pièce(s) jointe(s)') }}</strong>@endif.
                </flux:text>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close><flux:button variant="ghost">{{ __('Annuler') }}</flux:button></flux:modal.close>
                <flux:button variant="primary" wire:click="reply">
                    <span class="flex items-center gap-2">
                        <i class="hgi-stroke hgi-sent"></i>
                        <span>{{ __('Envoyer maintenant') }}</span>
                    </span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
