<?php

use App\Livewire\Concerns\NotifiesWithToast;
use App\Services\CosmiaApi;
use Flux\Flux;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Tiroir « Répondre à l'email » : rédaction, CC, liens, pièces jointes (FilePond), aide IA, envoi.
 * Ouvert par l'événement `compose-open` émis par la page ticket.
 * Après envoi, émet `ticket-updated` pour que la page recharge le ticket.
 * À la fermeture (annuler / croix / Échap / envoi), émet `compose-closed` : la page libère le verrou Socket.IO.
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

    /** Affiche le bandeau de confirmation d'envoi (en bas du tiroir) */
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

        // Le verrou « réponse en cours » est pris ICI, quand le tiroir s'ouvre réellement.
        // Si updatedShowComposeDrawer() émet un `compose-closed` tardif (synchro différée de wire:model)
        // dans la même réponse, il est émis AVANT ce `compose-opened` : le verrou reste donc détenu.
        $this->dispatch('compose-opened');
    }

    // Fermeture du drawer (croix, Échap, clic extérieur, Annuler) : on vide les pièces jointes
    // et on prévient la page pour qu'elle libère le verrou « réponse en cours ».
    // Attention : ce hook peut s'exécuter avec retard (synchro différée), d'où `compose-opened` dans open().
    public function updatedShowComposeDrawer(bool $open): void
    {
        if (! $open) {
            $this->photos        = [];
            $this->pendingFile   = null;
            $this->showSendModal = false;
            $this->dispatch('attachments-cleared');
            $this->dispatch('compose-closed');
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
            Log::warning('reply KO, vérification de l\'envoi', [
                'ticket' => $this->ticketId,
                'error'  => $e->getMessage(),
            ]);

            // L'API a peut-être envoyé le mail malgré l'erreur : on vérifie avant de conclure
            if (! $this->mailWasSent()) {
                $this->showSendModal = false;

                // On ne dit pas « échec » : l'envoi est incertain, et un renvoi risque un doublon
                $this->dispatch('ticket-updated');
                $this->notify(
                    __("L'envoi n'a pas pu être confirmé. Vérifiez l'onglet Conversation avant de renvoyer, pour éviter un doublon."),
                    'warning'
                );

                return;
            }
        }

        $this->finishSend($attachments);
    }

    /**
     * Après une erreur API : relit le ticket et compare le nombre de messages.
     * Plusieurs tentatives, car l'API peut encore être en train de traiter l'envoi.
     */
    private function mailWasSent(): bool
    {
        $before = (int) ($this->context['messages_count'] ?? 0);

        foreach ([2, 3, 3] as $wait) {
            sleep($wait);

            try {
                $data = app(CosmiaApi::class)->get("/ticket/{$this->ticketId}");
            } catch (\RuntimeException) {
                continue;
            }

            if (count($data['conversation']['messages'] ?? []) > $before) {
                return true;
            }
        }

        return false;
    }

    /** Remise à zéro du tiroir + toast de succès */
    private function finishSend(array $attachments): void
    {
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
        $this->dispatch('compose-closed');   // libère le verrou « réponse en cours »
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
{{-- compose-denied : émis par ticket-lock.js si quelqu'un d'autre a pris le verrou entre-temps -> on referme le tiroir --}}
<div @compose-denied.window="$wire.set('showComposeDrawer', false)">
    @assets
    <link href="https://unpkg.com/filepond@^4/dist/filepond.min.css" rel="stylesheet" />
    <link href="https://unpkg.com/filepond-plugin-image-preview@^4/dist/filepond-plugin-image-preview.min.css" rel="stylesheet" />
    <style>
        /* FilePond compact : zone de dépôt d'une ligne, fichiers en grille sur 2 colonnes, aperçu des images */
        .filepond--root { font-family: inherit; font-size: .75rem; margin-bottom: 0; }
        .filepond--drop-label { min-height: 2.75em !important; color: rgb(113 113 122); }
        .filepond--drop-label label { padding: .5em !important; cursor: pointer; }
        .dark .filepond--drop-label { color: rgb(161 161 170); }
        .filepond--panel-root { background-color: transparent; border: 1px dashed rgb(161 161 170 / .55); border-radius: .5rem; }
        .filepond--label-action { text-decoration-color: rgb(99 102 241); color: rgb(79 70 229); }
        .dark .filepond--label-action { color: rgb(165 180 252); }
        .filepond--item { width: calc(50% - .5rem); }
        @media (max-width: 480px) { .filepond--item { width: calc(100% - .5rem); } }
        .filepond--item-panel { background-color: rgb(99 102 241); border-radius: .5rem; }
        [data-filepond-item-state='processing-complete'] .filepond--item-panel { background-color: rgb(16 185 129); }
        [data-filepond-item-state*='error'] .filepond--item-panel { background-color: rgb(239 68 68); }
        .filepond--image-preview-wrapper { border-radius: .5rem; }
    </style>
    <script src="https://unpkg.com/filepond-plugin-image-preview@^4/dist/filepond-plugin-image-preview.min.js"></script>
    <script src="https://unpkg.com/filepond-plugin-file-validate-size@^2/dist/filepond-plugin-file-validate-size.min.js"></script>
    <script src="https://unpkg.com/filepond-plugin-file-validate-type@^1/dist/filepond-plugin-file-validate-type.min.js"></script>
    <script src="https://unpkg.com/filepond@^4/dist/filepond.min.js"></script>
    @endassets

    @php
        // Champs « compose » : une ligne, étiquette discrète à gauche, pas de bordure de champ
        $fieldRow   = 'flex items-center gap-3 border-b border-zinc-200 px-4 py-1.5 dark:border-white/10';
        $fieldLabel = 'w-10 shrink-0 text-xs text-zinc-500 dark:text-zinc-400';
        $fieldInput = 'min-w-0 flex-1 border-0 bg-transparent p-0 text-[13px] text-zinc-900 outline-none placeholder:text-zinc-400 focus:ring-0 read-only:text-zinc-700 dark:text-white dark:read-only:text-zinc-300';
        $miniBtn    = 'inline-flex items-center rounded-md px-1.5 py-1 text-xs font-medium text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white';
        $toolBtn    = 'inline-flex size-8 items-center justify-center rounded-md text-base text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500 disabled:opacity-50 dark:text-zinc-300 dark:hover:bg-white/10 dark:hover:text-white';
        $linkInput  = 'block w-full rounded-md border border-zinc-300 bg-white px-2.5 py-1.5 text-[13px] text-zinc-900 outline-none placeholder:text-zinc-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-white/15 dark:bg-zinc-900 dark:text-white';
    @endphp

    {{-- ========== Tiroir : réponse par email ========== --}}
    {{-- @close : filet de sécurité côté navigateur, libère le verrou même avant la synchro Livewire --}}
    <flux:modal
        wire:model.self="showComposeDrawer"
        variant="flyout"
        position="right"
        class="h-[80dvh] max-h-[80dvh] w-full max-w-full !p-0 md:w-[42rem]"
        @close="$dispatch('compose-closed')"
    >
        <div class="relative flex h-[80dvh] max-h-[80dvh] flex-col overflow-hidden">

            {{-- En-tête --}}
            <div class="flex min-h-[3.25rem] shrink-0 items-center border-b border-zinc-200 bg-zinc-50 py-2 pe-12 ps-4 dark:border-white/10 dark:bg-white/5">
                <div class="min-w-0">
                    <div class="truncate text-sm font-semibold leading-tight text-zinc-900 dark:text-white">{{ __("Répondre à l'email") }}</div>
                    <div class="truncate text-[11px] leading-tight text-zinc-500 dark:text-zinc-400">
                        {{ __('Ticket') }} {{ $context['num_ticket'] ?? $ticketId }}
                        @if (! empty($context['client_name'])) · {{ $context['client_name'] }} @endif
                        · {{ (int) ($context['messages_count'] ?? 0) }} {{ __('message(s)') }}
                    </div>
                </div>
            </div>

            {{-- Corps --}}
            <div class="flex min-h-0 flex-1 flex-col overflow-y-auto">

                {{-- À + Cc (la ligne Cc est toujours affichée sous À) --}}
                <div
                    x-data="{
                        val: '',
                        commit() {
                            this.val.split(/[,;\s]+/).filter(Boolean).forEach(e => this.$wire.addCC(e));
                            this.val = '';
                        },
                    }"
                >
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

                        <div class="flex shrink-0 items-center gap-0.5">
                            @if (! $editDestinataire)
                                <button type="button" wire:click="$set('editDestinataire', true)" class="{{ $miniBtn }}" title="{{ __('Modifier le destinataire') }}" aria-label="{{ __('Modifier le destinataire') }}">
                                    <i class="iconoir-edit-pencil" aria-hidden="true"></i>
                                </button>
                            @else
                                <button type="button" wire:click="$set('editDestinataire', false)" class="{{ $miniBtn }} !text-indigo-600 dark:!text-indigo-400">{{ __('Valider') }}</button>
                                <button type="button" wire:click="resetDestinataire" class="{{ $miniBtn }}">{{ __('Annuler') }}</button>
                            @endif
                        </div>
                    </div>

                    {{-- Cc (toujours visible, juste sous À) --}}
                    <div class="{{ $fieldRow }} items-start">
                        <span class="{{ $fieldLabel }} pt-1">{{ __('Cc') }}</span>

                        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-1">
                            @foreach ($cc as $index => $email)
                                <span wire:key="cc-{{ $index }}-{{ $email }}" class="inline-flex items-center gap-1 rounded-md bg-zinc-100 py-0.5 pe-1 ps-2 text-xs text-zinc-700 dark:bg-white/10 dark:text-zinc-200">
                                    {{ $email }}
                                    <button type="button" wire:click="removeCC({{ $index }})" class="flex items-center rounded p-0.5 text-zinc-500 hover:bg-zinc-200 hover:text-zinc-900 dark:hover:bg-white/15 dark:hover:text-white" aria-label="{{ __('Retirer') }}">
                                        <i class="iconoir-xmark text-[11px]" aria-hidden="true"></i>
                                    </button>
                                </span>
                            @endforeach

                            <input
                                type="email"
                                x-model="val"
                                @blur="commit()"
                                @keydown.enter.prevent="commit()"
                                @keydown.comma.prevent="commit()"
                                @keydown.semicolon.prevent="commit()"
                                @keydown.space.prevent="commit()"
                                placeholder="{{ __('Ajouter une adresse en copie…') }}"
                                class="min-w-[160px] flex-1 border-0 bg-transparent p-0.5 text-[13px] text-zinc-900 outline-none placeholder:text-zinc-400 focus:ring-0 dark:text-white"
                            />
                        </div>
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

                    <div class="flex shrink-0 items-center gap-0.5">
                        @if (! $editSubject)
                            <button type="button" wire:click="$set('editSubject', true)" class="{{ $miniBtn }}" title="{{ __("Modifier l'objet") }}" aria-label="{{ __("Modifier l'objet") }}">
                                <i class="iconoir-edit-pencil" aria-hidden="true"></i>
                            </button>
                        @else
                            <button type="button" wire:click="$set('editSubject', false)" class="{{ $miniBtn }} !text-indigo-600 dark:!text-indigo-400">{{ __('Valider') }}</button>
                            <button type="button" wire:click="resetSubject" class="{{ $miniBtn }}">{{ __('Annuler') }}</button>
                        @endif
                    </div>
                </div>

                {{-- Éditeur : occupe tout l'espace restant. Ctrl/⌘ + Entrée ouvre la confirmation d'envoi.
                     `insert-text` (émis par le bouton « Lien ») insère le texte à la position du curseur. --}}
                <textarea
                    x-data="{
                        insert(text) {
                            const el = $el;
                            const start = el.selectionStart ?? el.value.length;
                            const end = el.selectionEnd ?? start;
                            el.setRangeText(text, start, end, 'end');
                            el.dispatchEvent(new Event('input', { bubbles: true }));
                            el.focus();
                        },
                    }"
                    @insert-text.window="insert($event.detail)"
                    wire:model="message_txt"
                    placeholder="{{ __('Rédigez votre réponse au client…') }}"
                    @keydown.ctrl.enter.prevent="$wire.set('showSendModal', true)"
                    @keydown.meta.enter.prevent="$wire.set('showSendModal', true)"
                    class="block min-h-[14rem] w-full flex-1 resize-none border-0 bg-transparent px-4 py-3 text-sm leading-relaxed text-zinc-900 outline-none placeholder:text-zinc-400 focus:ring-0 dark:text-white"
                ></textarea>

                {{-- Message original du client : citation repliée sous l'éditeur --}}
                @if ($message_client !== '')
                    <div x-data="{ open: false }" class="shrink-0 px-4 pb-3">
                        <button
                            type="button"
                            @click="open = ! open"
                            class="inline-flex items-center gap-1.5 rounded-md bg-zinc-100 px-2 py-0.5 text-xs text-zinc-500 transition hover:bg-zinc-200 hover:text-zinc-800 dark:bg-white/10 dark:text-zinc-400 dark:hover:bg-white/15 dark:hover:text-white"
                            :aria-expanded="open"
                        >
                            <i class="iconoir-more-horiz" aria-hidden="true"></i>
                            <span x-text="open ? '{{ __('Masquer le message original') }}' : '{{ __('Message original du client') }}'"></span>
                        </button>

                        <div x-show="open" x-collapse x-cloak>
                            <div class="mt-2 max-h-32 overflow-y-auto whitespace-pre-line break-words border-l-2 border-zinc-300 ps-3 text-[13px] text-zinc-500 dark:border-white/20 dark:text-zinc-400">
                                {{ \Illuminate\Support\Str::limit($message_client, 600) }}
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Pièces jointes (FilePond) : zone compacte, aperçu des images, le trombone du pied ouvre le sélecteur --}}
            <div
                wire:ignore
                x-data="{
                    pond: null,
                    async init() {
                        const wire = this.$wire;

                        while (! (window.FilePond && window.FilePondPluginFileValidateSize && window.FilePondPluginFileValidateType && window.FilePondPluginImagePreview)) {
                            await new Promise(resolve => setTimeout(resolve, 50));
                        }

                        FilePond.registerPlugin(FilePondPluginFileValidateSize, FilePondPluginFileValidateType, FilePondPluginImagePreview);

                        this.pond = FilePond.create(this.$refs.input, {
                            allowMultiple: true,
                            maxParallelUploads: 1,
                            maxFileSize: '5MB',
                            credits: false,
                            acceptedFileTypes: ['image/*', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],

                            // Aperçu uniquement pour les images (les autres fichiers restent en pastille)
                            allowImagePreview: true,
                            imagePreviewHeight: 80,
                            imagePreviewMinHeight: 40,
                            imagePreviewMaxHeight: 96,
                            imagePreviewMaxFileSize: '5MB',
                            imagePreviewTransparencyIndicator: 'grid',

                            labelIdle: 'Glissez vos fichiers ici ou <span class=\'filepond--label-action\'>parcourir</span> · Images, PDF, Word · 5 Mo max',
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
                @attach-browse.window="pond?.browse()"
                class="max-h-56 shrink-0 overflow-y-auto border-t border-zinc-200 bg-zinc-50 px-4 py-2 dark:border-white/10 dark:bg-white/5"
            >
                <input type="file" x-ref="input" multiple />
            </div>

            {{-- Pied : envoi à gauche, outils au centre, abandon à droite (comme une fenêtre de rédaction) --}}
            <div class="flex shrink-0 items-center gap-1 border-t border-zinc-200 bg-white px-3 py-2 dark:border-white/10 dark:bg-zinc-800">
                <flux:button size="sm" variant="primary" color="green" wire:click="$set('showSendModal', true)">
                    <span class="flex items-center gap-1.5">
                        <i class="iconoir-send" aria-hidden="true"></i>
                        <span>{{ __('Envoyer') }}</span>
                    </span>
                </flux:button>

                <span class="mx-1 h-5 w-px bg-zinc-200 dark:bg-white/10" aria-hidden="true"></span>

                <button type="button" @click="$dispatch('attach-browse')" class="{{ $toolBtn }}" title="{{ __('Joindre des fichiers') }}" aria-label="{{ __('Joindre des fichiers') }}">
                    <i class="iconoir-attachment" aria-hidden="true"></i>
                </button>

                {{-- Insérer un lien : le texte du lien (facultatif) et l'adresse sont insérés à la position du curseur --}}
                <div
                    class="relative"
                    x-data="{
                        open: false,
                        url: '',
                        label: '',
                        error: '',
                        toggle() {
                            this.open = ! this.open;
                            this.error = '';
                            if (this.open) this.$nextTick(() => this.$refs.url.focus());
                        },
                        submit() {
                            let raw = this.url.trim();

                            if (raw === '') { this.error = 'Saisissez une adresse.'; return; }

                            // Sans protocole : adresse email -> mailto:, sinon https://
                            if (! /^[a-z][a-z0-9+.-]*:/i.test(raw)) {
                                raw = (raw.includes('@') && ! raw.includes('/') ? 'mailto:' : 'https://') + raw;
                            }

                            let parsed;
                            try { parsed = new URL(raw); } catch (e) { this.error = 'Adresse invalide.'; return; }

                            if (! ['http:', 'https:', 'mailto:'].includes(parsed.protocol)) {
                                this.error = 'Seuls les liens http, https et mailto sont autorisés.';
                                return;
                            }

                            const target = parsed.protocol === 'mailto:' ? parsed.pathname : raw;
                            const label = this.label.trim();

                            this.$dispatch('insert-text', label !== '' ? label + ' (' + target + ')' : target);

                            this.url = '';
                            this.label = '';
                            this.error = '';
                            this.open = false;
                        },
                    }"
                    @click.outside="open = false"
                >
                    <button type="button" @click="toggle()" class="{{ $toolBtn }}" :class="open && 'bg-zinc-100 dark:bg-white/10'" title="{{ __('Insérer un lien') }}" aria-label="{{ __('Insérer un lien') }}" :aria-expanded="open">
                        <i class="iconoir-link" aria-hidden="true"></i>
                    </button>

                    <div
                        x-show="open"
                        x-cloak
                        x-transition.opacity.duration.150ms
                        @keydown.escape.stop="open = false"
                        role="dialog"
                        aria-label="{{ __('Insérer un lien') }}"
                        class="absolute bottom-full left-0 z-30 mb-2 w-80 max-w-[calc(100vw-2rem)] rounded-lg border border-zinc-200 bg-white p-3 shadow-lg dark:border-white/10 dark:bg-zinc-800"
                    >
                        <div class="mb-2 text-xs font-semibold text-zinc-900 dark:text-white">{{ __('Insérer un lien') }}</div>

                        <div class="space-y-2">
                            <div>
                                <label class="mb-0.5 block text-[11px] text-zinc-500 dark:text-zinc-400" for="compose-link-url">{{ __('Adresse (URL)') }}</label>
                                <input
                                    id="compose-link-url"
                                    type="text"
                                    x-ref="url"
                                    x-model="url"
                                    @keydown.enter.prevent="submit()"
                                    @input="error = ''"
                                    placeholder="https://exemple.com/page"
                                    class="{{ $linkInput }}"
                                />
                            </div>

                            <div>
                                <label class="mb-0.5 block text-[11px] text-zinc-500 dark:text-zinc-400" for="compose-link-label">{{ __('Texte du lien (facultatif)') }}</label>
                                <input
                                    id="compose-link-label"
                                    type="text"
                                    x-model="label"
                                    @keydown.enter.prevent="submit()"
                                    placeholder="{{ __('Ex. : Suivre ma commande') }}"
                                    class="{{ $linkInput }}"
                                />
                            </div>

                            <p x-show="error" x-text="error" x-cloak class="text-xs text-red-600 dark:text-red-400" role="alert"></p>
                        </div>

                        <div class="mt-3 flex items-center justify-end gap-2">
                            <button type="button" @click="open = false" class="{{ $miniBtn }}">{{ __('Annuler') }}</button>
                            <button type="button" @click="submit()" class="inline-flex items-center rounded-md bg-indigo-600 px-2.5 py-1 text-xs font-medium text-white transition hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500">
                                {{ __('Insérer') }}
                            </button>
                        </div>
                    </div>
                </div>

                <button type="button" wire:click="correctionOpenAI" wire:loading.attr="disabled" wire:target="correctionOpenAI" class="{{ $toolBtn }}" title="{{ __('Suggestion : corriger et améliorer le texte') }}" aria-label="{{ __('Suggestion') }}">
                    <i class="iconoir-magic-wand" wire:loading.remove wire:target="correctionOpenAI" aria-hidden="true"></i>
                    <i class="iconoir-refresh animate-spin" wire:loading wire:target="correctionOpenAI" aria-hidden="true"></i>
                </button>

                <button type="button" wire:click="translateOpenAI" wire:loading.attr="disabled" wire:target="translateOpenAI" class="{{ $toolBtn }}" title="{{ __('Traduire dans la langue du client') }}" aria-label="{{ __('Traduire') }}">
                    <i class="iconoir-language" wire:loading.remove wire:target="translateOpenAI" aria-hidden="true"></i>
                    <i class="iconoir-refresh animate-spin" wire:loading wire:target="translateOpenAI" aria-hidden="true"></i>
                </button>

                <flux:spacer />

                <span class="hidden text-[11px] text-zinc-400 sm:inline">{{ __('Ctrl + Entrée pour envoyer') }}</span>

                <flux:modal.close>
                    <button type="button" class="{{ $toolBtn }} hover:!text-red-600 dark:hover:!text-red-400" title="{{ __('Abandonner le brouillon') }}" aria-label="{{ __('Abandonner le brouillon') }}">
                        <i class="iconoir-trash" aria-hidden="true"></i>
                    </button>
                </flux:modal.close>
            </div>

            {{-- Confirmation d'envoi : bandeau qui monte depuis le bas du tiroir --}}
            <div
                x-cloak
                x-show="$wire.showSendModal"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="translate-y-full opacity-0"
                x-transition:enter-end="translate-y-0 opacity-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="translate-y-0 opacity-100"
                x-transition:leave-end="translate-y-full opacity-0"
                role="alertdialog"
                aria-live="assertive"
                aria-label="{{ __("Confirmer l'envoi") }}"
                class="absolute inset-x-0 bottom-0 z-20 border-t border-zinc-200 bg-white px-4 py-2.5 shadow-[0_-8px_24px_-12px_rgba(0,0,0,0.25)] dark:border-white/10 dark:bg-zinc-800"
            >
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __("Confirmer l'envoi ?") }}</div>
                        <div class="break-words text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __("L'email sera envoyé à") }}
                            <strong class="text-zinc-700 dark:text-zinc-200">{{ $destinateur }}</strong>@if (! empty($cc)), {{ __('en copie :') }} <strong class="text-zinc-700 dark:text-zinc-200">{{ implode(', ', $cc) }}</strong>@endif @if (! empty($photos))· {{ __('avec') }} <strong class="text-zinc-700 dark:text-zinc-200">{{ count($photos) }} {{ __('pièce(s) jointe(s)') }}</strong>@endif.
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <flux:button size="sm" wire:click="$set('showSendModal', false)" wire:loading.attr="disabled" wire:target="reply">
                            {{ __('Revenir') }}
                        </flux:button>

                        <flux:button size="sm" variant="primary" color="green" wire:click="reply" wire:loading.attr="disabled" wire:target="reply">
                            <span class="flex items-center gap-1.5">
                                <i class="iconoir-send" wire:loading.remove wire:target="reply" aria-hidden="true"></i>
                                <i class="iconoir-refresh animate-spin" wire:loading wire:target="reply" aria-hidden="true"></i>
                                <span wire:loading.remove wire:target="reply">{{ __('Envoyer maintenant') }}</span>
                                <span wire:loading wire:target="reply">{{ __('Envoi en cours…') }}</span>
                            </span>
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    </flux:modal>
</div>
