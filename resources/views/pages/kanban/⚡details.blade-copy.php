<?php

use App\Services\CosmiaApi;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public const STATUSES = [
        'en attente' => ['title' => 'En attente', 'color' => 'amber'],
        'en cours'   => ['title' => 'En cours',   'color' => 'blue'],
        'cloture'    => ['title' => 'Clôturé',    'color' => 'green'],
    ];

    // Reçoit l'id via /kanban/details?ticket=12423
    #[Url]
    public string $ticket = '';

    public int $ticketId = 0;

    public array $ticketDetails = [];

    public ?string $error = null;

    public string $activeTab = 'description';

    // Modales
    public bool $showReadModal = false;

    public bool $showSendModal = false;

    // Conversation
    public ?int $selectedMessageIndex = null;

    public string $translatedMessage = '';

    // Envoi mail (drawer extra-large)
    public bool $showComposeDrawer = false;

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

    // Chatbot
    public bool $chatbotMessageTab = false;

    public array $messagesChatBot = [];

    public ?string $conversation_chat_id = null;

    public int $pageChat = 1;

    public bool $hasMoreMessages = true;

    public function mount(): void
    {
        if ($this->ticket === '' || ! ctype_digit($this->ticket)) {
            $this->redirectRoute('kanban', navigate: true);

            return;
        }

        $this->ticketId = (int) $this->ticket;
        $this->fetchTicketDetails();
    }

    /* ------------------------------------------------------------------ */
    /*  Computed                                                           */
    /* ------------------------------------------------------------------ */

    // Les icônes sont des noms Hugeicons (classe CSS : hgi-{nom})
    #[Computed]
    public function tabs(): array
    {
        $tabs = [
            'description' => [
                'label' => 'Ticket - '.($this->ticketDetails['details'][0]['num_ticket'] ?? ''),
                'icon'  => 'file-01',
            ],
        ];

        if ($this->chatbotMessageTab) {
            $tabs['chatbot'] = ['label' => 'Conversation chatbot', 'icon' => 'message-01'];
        }

        $tabs['conversation'] = [
            'label' => 'Conversation ('.count($this->ticketDetails['conversation']['messages'] ?? []).')',
            'icon'  => 'mail-01',
        ];
        $tabs['commentaire'] = ['label' => "Historique d'actions", 'icon' => 'clock-01'];

        return $tabs;
    }

    #[Computed]
    public function nextStatus(): array
    {
        return match ($this->ticketDetails['details'][0]['status'] ?? 'en attente') {
            'en attente' => ['label' => 'Mettre en cours',        'next' => 'en cours'],
            'en cours'   => ['label' => 'Clôturer le ticket',     'next' => 'cloture'],
            'cloture'    => ['label' => 'Réouvrir (en attente)',  'next' => 'en attente'],
            default      => ['label' => 'Mettre en attente',      'next' => 'en attente'],
        };
    }

    /* ------------------------------------------------------------------ */
    /*  Chargement                                                         */
    /* ------------------------------------------------------------------ */

    private function fetchTicketDetails(bool $resetForm = true): void
    {
        try {
            $data = app(CosmiaApi::class)->get("/ticket/{$this->ticketId}");
        } catch (\RuntimeException $e) {
            if ($this->ticketDetails === []) {
                $this->error = $e->getMessage();
            } else {
                $this->notify($e->getMessage(), 'danger');
            }

            return;
        }

        $detail = $data['details'][0] ?? null;

        if (! $detail) {
            $this->error = __('Ticket introuvable.');

            return;
        }

        // Champs texte du ticket : entités HTML / mauvais encodage corrigés une fois pour toutes
        foreach (['subject_ticket', 'nom_client', 'num_commande', 'num_ticket', 'reception_mail', 'original_client_mail', 'to_do'] as $key) {
            if (isset($data['details'][0][$key]) && is_string($data['details'][0][$key])) {
                $data['details'][0][$key] = $this->decode($data['details'][0][$key]);
            }
        }

        $detail = $data['details'][0];

        $this->error         = null;
        $this->ticketDetails = $data;

        if ($resetForm) {
            $this->destinateur         = trim((string) ($detail['original_client_mail'] ?? ''));
            $this->destinateurOriginal = $this->destinateur;

            $firstSubject = trim($this->decode($data['conversation']['messages'][0]['subject'] ?? ''));
            $this->subject         = $firstSubject !== '' ? $firstSubject : trim($this->decode($detail['subject_ticket'] ?? ''));
            $this->subjectOriginal = $this->subject;
        }

        // Chatbot : on repart de la première page à chaque rechargement (évite les doublons)
        $this->messagesChatBot      = [];
        $this->pageChat             = 1;
        $this->hasMoreMessages      = true;
        $this->conversation_chat_id = ! empty($detail['conversation_chat_id']) ? (string) $detail['conversation_chat_id'] : null;
        $this->chatbotMessageTab    = $this->conversation_chat_id !== null;

        if ($this->chatbotMessageTab) {
            $this->getChatbotConversation();
        }
    }

    private function getChatbotConversation(): void
    {
        try {
            $data = app(CosmiaApi::class)->post('/chatbot/getchatmessage', [
                'conversation_chat_id' => $this->conversation_chat_id,
                'page'                 => $this->pageChat,
            ]);
        } catch (\RuntimeException $e) {
            $this->notify($e->getMessage(), 'danger');

            return;
        }

        $new = $data['messages'] ?? [];

        if (empty($new)) {
            $this->hasMoreMessages = false;

            return;
        }

        $this->messagesChatBot = array_merge($this->messagesChatBot, $new);
    }

    public function loadOlderMessages(): void
    {
        if (! $this->hasMoreMessages || ! $this->conversation_chat_id) {
            return;
        }

        $this->pageChat++;
        $this->getChatbotConversation();
    }

    /* ------------------------------------------------------------------ */
    /*  Navigation / statut                                                */
    /* ------------------------------------------------------------------ */

    public function setTab(string $tab): void
    {
        if (array_key_exists($tab, $this->tabs)) {
            $this->activeTab = $tab;
        }
    }

    // Fermeture du drawer côté client (croix, Échap, clic extérieur) : on vide les pièces jointes
    // (FilePond repart de zéro à la réouverture)
    public function updatedShowComposeDrawer(bool $open): void
    {
        if (! $open) {
            $this->photos      = [];
            $this->pendingFile = null;
            $this->dispatch('attachments-cleared');
        }
    }

    public function updateStatus(string $newStatus): void
    {
        if (! isset(self::STATUSES[$newStatus])) {
            return;
        }

        try {
            app(CosmiaApi::class)->put("/ticket/{$this->ticketId}", ['status' => $newStatus]);
        } catch (\RuntimeException) {
            $this->notify(__('Impossible de mettre à jour le ticket !'), 'danger');

            return;
        }

        $this->notify(__('Le ticket est maintenant en statut : :status', ['status' => $newStatus]));
        $this->fetchTicketDetails(false);
    }

    public function confirmerActionLu(): void
    {
        try {
            app(CosmiaApi::class)->post('/ticket/ignoreclientresponse', ['ticket_id' => $this->ticketId]);
        } catch (\RuntimeException $e) {
            $this->notify(__('Erreur lors de la mise à jour : ').$e->getMessage(), 'danger');

            return;
        }

        $this->notify(__('Tous les messages ont été marqués comme lus'));
        $this->showReadModal = false;
        $this->fetchTicketDetails(false);
    }

    /* ------------------------------------------------------------------ */
    /*  Conversation                                                       */
    /* ------------------------------------------------------------------ */

    public function openMessage(int $index): void
    {
        if (isset($this->ticketDetails['conversation']['messages'][$index])) {
            $this->selectedMessageIndex = $index;
            $this->translatedMessage    = '';
        }
    }

    public function showOriginalMessage(): void
    {
        $this->translatedMessage = '';
    }

    public function translateMessage(): void
    {
        $text = (string) ($this->ticketDetails['conversation']['messages'][$this->selectedMessageIndex ?? -1]['message'] ?? '');

        if ($text === '') {
            $this->translatedMessage = __('Aucun message à traduire.');

            return;
        }

        try {
            $res = app(CosmiaApi::class)->post('/openai/translateandcorrect', ['text' => $text, 'target' => 'fr']);
            $this->translatedMessage = $this->formatMessage((string) ($res['translated_text'] ?? __('Erreur de traduction')));
        } catch (\RuntimeException) {
            $this->translatedMessage = __("Erreur lors de l'appel API");
        }
    }

    public function replyFirstMessage(): void
    {
        $first = $this->ticketDetails['conversation']['messages'][0] ?? null;

        if ($first) {
            $this->message_client = trim(preg_replace('/\n{3,}/', "\n\n", $this->decode(strip_tags($this->structureText((string) ($first['message'] ?? ''))))) ?? '');
        }

        $this->showComposeDrawer = true;
    }

    public function writeNewMessage(): void
    {
        $this->showComposeDrawer = true;
    }

    /* ------------------------------------------------------------------ */
    /*  Envoi mail                                                         */
    /* ------------------------------------------------------------------ */

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

        if (! empty($this->photos) && empty($attachments)) {
            $this->showSendModal = false;
            $this->notify(__("Aucun fichier valide n'a pu être traité"), 'danger');

            return;
        }

        $messages = $this->ticketDetails['conversation']['messages'] ?? [];

        $body = [
            'ticket_id'    => $this->ticketId,
            'replyText'    => $this->message_txt,
            'attachements' => $attachments,
            'destinataire' => $this->destinateur,
            'cc'           => $this->cc,
            'subject'      => $this->subject,
        ];

        // Réponse à un fil existant, ou nouveau mail si la conversation est vide
        if (count($messages) > 0) {
            $body['first_message_id'] = $messages[0]['message_id'] ?? null;
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
        $this->dispatch('attachments-cleared');
        $this->cc                = [];

        $this->fetchTicketDetails();

        // Toast d'envoi : le rendu sombre/clair inversé vient de <flux:toast invert /> dans le layout
        Flux::toast(
            heading: __('Email envoyé'),
            text: $attachCount > 0
                ? __('Votre réponse a été envoyée à :email avec :count pièce(s) jointe(s).', ['email' => $sentTo, 'count' => $attachCount])
                : __('Votre réponse a été envoyée à :email.', ['email' => $sentTo]),
            variant: 'success',
            duration: 6000,
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers utilisés par la vue                                        */
    /* ------------------------------------------------------------------ */

    private function notify(string $message, ?string $variant = null): void
    {
        // variantes Flux : success | warning | danger (null = neutre)
        $variant === null || $variant === 'info'
            ? Flux::toast(text: $message)
            : Flux::toast(text: $message, variant: $variant);
    }

    // Nettoie un texte venant de l'API : entités HTML (&#39; &amp;#39; &quot;…) puis mojibake (Ã©, â€™…)
    public function decode(?string $value): string
    {
        $v = (string) $value;

        // Plusieurs passes : gère le double encodage (&amp;#39; -> &#39; -> ')
        for ($i = 0; $i < 3; $i++) {
            $decoded = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($decoded === $v) {
                break;
            }

            $v = $decoded;
        }

        // Texte UTF-8 mal interprété en Windows-1252 (« rÃ©ponse », « dÃƒÂ©fectueux » = double encodage)
        for ($i = 0; $i < 3 && preg_match('/[ÃÂâ]/u', $v); $i++) {
            $fixed = @mb_convert_encoding($v, 'Windows-1252', 'UTF-8');

            if ($fixed === false || $fixed === $v || ! mb_check_encoding($fixed, 'UTF-8')) {
                break;
            }

            // Garde-fou : on ne corrige que si la conversion est réversible (évite d'abîmer du texte légitime)
            if (mb_convert_encoding($fixed, 'UTF-8', 'Windows-1252') !== $v) {
                break;
            }

            $v = $fixed;
        }

        return $v;
    }

    // Format : 25 Janvier 2026 à 14:30
    public function formatDate(?string $value, bool $withTime = true): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            $c = Carbon::parse(preg_replace('/\s*\([^)]+\)\s*$/', '', $value))
                ->setTimezone('Europe/Paris')
                ->locale('fr');
        } catch (\Throwable) {
            return $value;
        }

        $date = $c->day.' '.mb_convert_case($c->translatedFormat('F'), MB_CASE_TITLE).' '.$c->year;

        return $withTime ? $date.' à '.$c->format('H:i') : $date;
    }

    public function messageMeta(array $msg): array
    {
        $from  = $this->decode($msg['from'] ?? '');
        $to    = $this->decode($msg['to'] ?? '');
        $email = strtolower(trim(preg_match('/<([^>]+)>/', $from, $m) ? $m[1] : $from));

        $client  = strtolower(trim((string) ($this->ticketDetails['details'][0]['original_client_mail'] ?? '')));
        $support = strtolower(trim((string) ($this->ticketDetails['details'][0]['reception_mail'] ?? '')));

        $role = null;

        if ($client !== '' && str_contains($email, $client)) {
            $role = 'client';
        } elseif ($support !== '' && str_contains($email, $support)) {
            $role = 'support';
        }

        $clean = fn (string $v) => trim(trim(preg_replace('/\s*<[^>]*>/', '', $v)) ?: $v, "\" '");

        return [
            'name'    => $clean($from) ?: '-',
            'to'      => $clean($to) ?: '-',
            'role'    => $role,
            'date'    => $this->formatDate($msg['date'] ?? null),
            'subject' => trim($this->decode($msg['subject'] ?? '')) ?: __('(Sans objet)'),
            'preview' => trim(preg_replace('/\s+/u', ' ', $this->decode(strip_tags($this->structureText((string) ($msg['message'] ?? ''))))) ?? ''),
        ];
    }

    public function attachmentInfo(array $a): array
    {
        $filename = (string) ($a['filename'] ?? 'Fichier sans nom');
        $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime     = (string) ($a['mimeType'] ?? '');

        return [
            'name'    => preg_replace('/^[0-9a-f]{16}_/', '', $filename),
            'ext'     => $ext,
            'url'     => $a['url'] ?? null,
            'size'    => ! empty($a['size']) ? $this->formatFileSize((int) $a['size']) : null,
            'isImage' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) || str_contains($mime, 'image'),
        ];
    }

    public function presentComment(array $c): array
    {
        $raw  = trim((string) ($c['comment'] ?? ''));
        $type = 'info';

        if (preg_match('/^\[(\w+)\]\s*(.*)$/su', $raw, $m)) {
            $type = strtolower($m[1]);
            $raw  = trim($m[2]);
        }

        $ago = null;

        try {
            $ago = Carbon::parse($c['created_at'])->locale('fr')->diffForHumans();
        } catch (\Throwable) {
        }

        return [
            'type' => $type,
            'text' => $this->decode($raw),
            'date' => $this->formatDate($c['created_at'] ?? null),
            'ago'  => $ago,
        ];
    }

    public function formatFileSize(int $bytes): string
    {
        return match (true) {
            $bytes >= 1073741824 => number_format($bytes / 1073741824, 2).' Go',
            $bytes >= 1048576    => number_format($bytes / 1048576, 2).' Mo',
            $bytes >= 1024       => number_format($bytes / 1024, 2).' Ko',
            default              => $bytes.' octets',
        };
    }

    // Garde la structure d'un mail HTML (paragraphes, cellules de tableau) sous forme de retours à la ligne,
    // et retire styles/scripts pour qu'ils ne s'affichent pas comme du texte
    private function structureText(string $html): string
    {
        $html = preg_replace('~<(style|script|head)\b[^>]*>.*?</\1>~is', '', $html) ?? $html;
        $html = preg_replace('~<br\b[^>]*>~i', "\n", $html) ?? $html;
        $html = preg_replace('~</(p|div|tr|li|h[1-6]|table|ul|ol|blockquote)>~i', "\n", $html) ?? $html;
        $html = preg_replace('~</t[dh]>~i', ' ', $html) ?? $html;

        return $html;
    }

    // Texte -> HTML sûr : balises retirées, texte échappé, liens cliquables. Les retours à la ligne restent des \n.
    private function renderText(string $message): string
    {
        $links = [];

        $message = $this->structureText($message);

        $message = preg_replace_callback(
            '~<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>~is',
            function ($m) use (&$links) {
                $links[] = ['url' => trim($this->decode($m[1])), 'text' => trim($this->decode(strip_tags($m[2])))];

                return '___LINK_'.(count($links) - 1).'___';
            },
            $message
        );

        $message = e($this->decode(strip_tags($message)));

        // Espaces insécables / caractères invisibles, retours à la ligne propres
        $message = preg_replace('/[\x{00A0}\x{2007}\x{202F}]/u', ' ', $message) ?? $message;
        $message = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', '', $message) ?? $message;
        $message = str_replace(["\r\n", "\r"], "\n", $message);
        $message = preg_replace('/[ \t]+\n/', "\n", $message);
        $message = preg_replace('/\n{3,}/', "\n\n", $message);

        $class = 'text-blue-600 underline dark:text-blue-400';

        // URLs brutes
        $message = preg_replace_callback(
            '~\b(?:https?://|www\.)[^\s]*[^\s.,;:!?)\]]~i',
            function ($m) use ($class) {
                $href = preg_match('~^https?://~i', $m[0]) ? $m[0] : 'http://'.$m[0];

                return '<a href="'.$href.'" target="_blank" rel="noopener noreferrer" class="'.$class.'">'.$m[0].'</a>';
            },
            $message
        );

        // Liens HTML d'origine
        foreach ($links as $i => $link) {
            $url  = $link['url'];
            $text = e($link['text'] !== '' ? $link['text'] : $url);

            if (preg_match('~^(https?://|mailto:)~i', $url)) {
                $html = '<a href="'.e($url).'" target="_blank" rel="noopener noreferrer" class="'.$class.'">'.$text.'</a>';
            } elseif (! preg_match('~^[a-z][a-z0-9+.\-]*:~i', $url)) {
                $html = '<a href="'.e('http://'.$url).'" target="_blank" rel="noopener noreferrer" class="'.$class.'">'.$text.'</a>';
            } else {
                $html = $text; // schéma non autorisé (javascript:, data:…)
            }

            $message = str_replace('___LINK_'.$i.'___', $html, $message);
        }

        return trim($message);
    }

    // Texte -> HTML sûr : balises retirées, texte échappé, liens cliquables, retours à la ligne (utilisé par le chatbot)
    public function formatMessage(string $message): string
    {
        return nl2br($this->renderText($message));
    }

    // Découpe un mail en corps / signature / historique cité, pour un affichage lisible dans le panneau de lecture
    public function presentMessage(string $message): array
    {
        $lines  = explode("\n", $this->renderText($message));
        $plain  = fn (string $l): string => trim(strip_tags($l));
        $quoted = [];

        // 1. Historique cité (« Le … a écrit : », « De : … Envoyé : … », lignes citées « > »)
        foreach ($lines as $i => $line) {
            if ($i === 0) {
                continue;
            }

            $text   = $plain($line);
            $nextTx = $plain(($lines[$i + 1] ?? '').' '.($lines[$i + 2] ?? ''));

            $isQuote = preg_match('~^(le|on)\s.{6,250}(a écrit|wrote)\s*:?$~iu', $text)
                || preg_match('~^-{2,}\s*(original message|message d.origine|forwarded message|message transf[ée]r[ée])~iu', $text)
                || str_starts_with($text, '&gt;')
                || (preg_match('~^(de|from)\s*:\s*\S~iu', $text) && preg_match('~^(envoy[ée]|sent|date|objet|subject)\s*:~iu', $nextTx));

            if ($isQuote) {
                $top = array_slice($lines, 0, $i);

                if ($plain(implode('', $top)) !== '') {
                    $quoted = array_slice($lines, $i);
                    $lines  = $top;
                }

                break;
            }
        }

        // 2. Signature : délimiteur « -- », « Envoyé de mon… », ou bloc après une formule de politesse
        $closing = '~^(?:(?:bien\s+)?cordialement(?:\s+v[oô]tre)?|bien\s+[àa]\s+vous|amicalement|sinc[èe]res\s+salutations|salutations\s+(?:distingu[ée]es|cordiales|respectueuses)|meilleures\s+salutations|respectueusement|bonne\s+(?:journ[ée]e|soir[ée]e|r[ée]ception)|(?:best|kind|warm|with)\s+regards|regards|sincerely|best\s+wishes|yours\s+(?:truly|sincerely|faithfully))[\s,.!;:\-]*$~iu';

        $signature = [];
        $count     = count($lines);

        for ($i = max(0, $count - 25); $i < $count; $i++) {
            $text  = $plain($lines[$i]);
            $start = null;

            if (preg_match('~^(--|__+)\s*$~u', $text)) {
                $start = $i;
            } elseif (preg_match('~^(envoy[ée] de mon|envoy[ée] depuis|sent from my|get outlook for|obtenir outlook)~iu', $text)) {
                $start = $i;
            } elseif (mb_strlen($text) <= 45 && preg_match($closing, $text)) {
                $start = $i + 1; // la formule de politesse reste dans le corps
            }

            if ($start !== null) {
                $rest = array_slice($lines, $start);

                if ($plain(implode('', $rest)) !== '' && count($rest) <= 20) {
                    $signature = $rest;
                    $lines     = array_slice($lines, 0, $start);

                    break;
                }
            }
        }

        $html = fn (array $part): ?string => ($t = trim(implode("\n", $part))) !== ''
            ? nl2br(preg_replace('/\n{3,}/', "\n\n", $t))
            : null;

        return [
            'body'      => $html($lines) ?? '',
            'signature' => $html($signature),
            'quoted'    => $html($quoted),
        ];
    }
};
?>
<div class="space-y-6">
    @if ($error)
        <flux:callout variant="danger" icon="exclamation-circle" :heading="$error" />
    @else
        @php
            $details  = $ticketDetails['details'][0] ?? [];
            $messages = $ticketDetails['conversation']['messages'] ?? [];
            $comments = $ticketDetails['comment'] ?? [];
            $tabs     = $this->tabs;
        @endphp

        {{-- Tickets groupés --}}

        {{-- Mobile : colonne (select au-dessus) / Desktop : onglets à gauche, contenu à droite --}}
        <div class="flex flex-col gap-6 sm:flex-row sm:items-start">

            {{-- Mobile : liste déroulante --}}
            <div class="grid grid-cols-1 sm:hidden">
                <select
                    wire:model.live="activeTab"
                    aria-label="Select a tab"
                    class="col-start-1 row-start-1 w-full appearance-none rounded-md bg-white py-2.5 pr-8 pl-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-zinc-800 dark:text-white dark:outline-white/10"
                >
                    @foreach ($tabs as $key => $tab)
                        <option value="{{ $key }}">{{ $tab['label'] }}</option>
                    @endforeach
                </select>
                <svg class="pointer-events-none col-start-1 row-start-1 mr-2 size-5 self-center justify-self-end fill-gray-500" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M4.22 6.22a.75.75 0 0 1 1.06 0L8 8.94l2.72-2.72a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0L4.22 7.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                </svg>
            </div>

            {{-- Desktop : onglets verticaux à gauche --}}
            <nav
                class="hidden sm:sticky sm:top-4 sm:block sm:w-52 sm:shrink-0 lg:w-60"
                aria-label="Tabs"
            >
                <div class="flex flex-col gap-1">
                    @foreach ($tabs as $key => $tab)
                        <button
                            type="button"
                            wire:key="tab-{{ $key }}"
                            wire:click="setTab('{{ $key }}')"
                            @if ($activeTab === $key) aria-current="page" @endif
                            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium transition-colors
                                {{ $activeTab === $key
                                    ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-400/10 dark:text-indigo-300'
                                    : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/5 dark:hover:text-zinc-200' }}"
                        >
                            <span class="flex shrink-0 items-center" wire:loading.remove wire:target="setTab('{{ $key }}')">
                                <i class="hgi-stroke hgi-{{ $tab['icon'] }}"></i>
                            </span>
                            <span class="flex shrink-0 items-center" wire:loading wire:target="setTab('{{ $key }}')">
                                <i class="hgi-stroke hgi-loading-03 animate-spin"></i>
                            </span>
                            <span class="min-w-0 flex-1 leading-snug">{{ $tab['label'] }}</span>
                        </button>
                    @endforeach
                </div>
            </nav>

            {{-- Contenu --}}
            <div class="min-w-0 flex-1 {{ $activeTab === 'conversation' ? 'pb-28' : '' }}">

                {{-- ========== Ticket (description) ========== --}}
                @if ($activeTab === 'description')
                    @php
                        $status = $this::STATUSES[$details['status'] ?? ''] ?? ['title' => ($details['status'] ?? '—'), 'color' => 'zinc'];
                        $next   = $this->nextStatus;
                        $todos  = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($details['to_do'] ?? ''))));

                        $fields = [
                            __('Statut')              => $status['title'],
                            __('N° ticket')           => $details['num_ticket'] ?? null,
                            __('N° de commande')      => $details['num_commande'] ?? null,
                            __('Objet')               => $details['subject_ticket'] ?? null,
                            __('E-mail du client')    => trim((string) ($details['original_client_mail'] ?? '')),
                            __('E-mail de réception') => $details['reception_mail'] ?? null,
                            __('Nom du client')       => $details['nom_client'] ?? null,
                        ];
                    @endphp

                    <div class="space-y-6">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge :color="$status['color']" size="sm">{{ $status['title'] }}</flux:badge>

                                @if (! empty($details['need_attention']))
                                    <flux:badge color="amber" size="sm">
                                        <span class="flex items-center gap-1">
                                            <i class="hgi-stroke hgi-alert-02"></i>
                                            <span>{{ __('Attention') }}</span>
                                        </span>
                                    </flux:badge>
                                @endif
                            </div>

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

                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="rounded-lg border border-zinc-200 dark:border-white/10">
                                <div class="border-b border-zinc-200 px-4 py-4 dark:border-white/10">
                                    <flux:heading>{{ __('Infos ticket') }}</flux:heading>
                                    <flux:text class="mt-1 text-sm">{{ __('Toutes les informations sur le ticket.') }}</flux:text>
                                </div>

                                <dl class="divide-y divide-zinc-200 dark:divide-white/10">
                                    @foreach ($fields as $label => $value)
                                        <div class="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4">
                                            <dt class="text-sm font-medium text-zinc-900 dark:text-white">{{ $label }}</dt>
                                            <dd class="mt-1 break-words text-sm text-zinc-600 sm:col-span-2 sm:mt-0 dark:text-zinc-300">{{ $value ?: '—' }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </div>

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
                        </div>
                    </div>
                @endif

                {{-- ========== Conversation chatbot ========== --}}
                @if ($activeTab === 'chatbot')
                    <div class="mx-auto max-w-3xl space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <flux:heading size="lg">{{ __('Historique du chatbot') }}</flux:heading>

                            <flux:button variant="primary" size="sm" wire:click="writeNewMessage">
                                <span class="flex items-center gap-2">
                                    <i class="hgi-stroke hgi-arrow-turn-backward"></i>
                                    <span>{{ __('Répondre') }}</span>
                                </span>
                            </flux:button>
                        </div>

                        <div
                            wire:key="chat-{{ $ticketId }}"
                            x-data="{ prev: 0 }"
                            x-init="$nextTick(() => $refs.box.scrollTop = $refs.box.scrollHeight)"
                        >
                            <div
                                x-ref="box"
                                class="flex h-[calc(100dvh-19rem)] min-h-96 flex-col gap-4 overflow-y-auto rounded-xl border border-zinc-200 bg-zinc-100 p-4 dark:border-white/10 dark:bg-zinc-900"
                            >
                                @if ($hasMoreMessages)
                                    <div class="sticky top-0 z-10 flex justify-center">
                                        <button
                                            type="button"
                                            @click="
                                                prev = $refs.box.scrollHeight;
                                                $wire.loadOlderMessages().then(() => $nextTick(() => $refs.box.scrollTop = $refs.box.scrollHeight - prev));
                                            "
                                            wire:loading.attr="disabled"
                                            wire:target="loadOlderMessages"
                                            class="rounded-full border border-zinc-300 bg-white px-4 py-1.5 text-xs text-zinc-700 shadow-sm hover:bg-zinc-50 dark:border-white/10 dark:bg-zinc-800 dark:text-zinc-200"
                                        >
                                            <span wire:loading.remove wire:target="loadOlderMessages">{{ __('Voir les messages plus anciens') }}</span>
                                            <span wire:loading wire:target="loadOlderMessages">{{ __('Chargement…') }}</span>
                                        </button>
                                    </div>
                                @else
                                    <div class="py-2 text-center text-xs text-zinc-400">{{ __('Début de la conversation') }}</div>
                                @endif

                                @forelse (collect($messagesChatBot)->sortBy('date_created')->values() as $i => $msg)
                                    @php $isClient = ($msg['acteur'] ?? '') === 'client'; @endphp

                                    <div wire:key="chatmsg-{{ $i }}-{{ $msg['date_created'] ?? '' }}" class="flex {{ $isClient ? 'justify-start' : 'justify-end' }}">
                                        <div class="flex max-w-[80%] flex-col {{ $isClient ? 'items-start' : 'items-end' }}">
                                            <div class="mb-1 flex items-center gap-2 {{ $isClient ? '' : 'flex-row-reverse' }}">
                                                <span class="flex size-6 items-center justify-center rounded-full text-xs font-bold text-white {{ $isClient ? 'bg-blue-500' : 'bg-emerald-500' }}">
                                                    {{ $isClient ? 'C' : 'IA' }}
                                                </span>
                                                <span class="text-xs text-zinc-500">{{ $this->formatDate($msg['date_created'] ?? null) }}</span>
                                            </div>

                                            <div class="break-words rounded-2xl px-4 py-2 text-sm
                                                {{ $isClient
                                                    ? 'rounded-bl-sm bg-blue-500 text-white [&_a]:!text-white'
                                                    : 'rounded-br-sm border border-zinc-200 bg-white text-zinc-800 dark:border-white/10 dark:bg-zinc-800 dark:text-zinc-100' }}">
                                                {!! $this->formatMessage(trim((string) ($msg['message'] ?? ''))) !!}
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="py-6 text-center text-sm text-zinc-400">{{ __('Aucun message dans cette conversation.') }}</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endif

                {{-- ========== Conversation e-mail ========== --}}
                @if ($activeTab === 'conversation')
                    @php $selected = $selectedMessageIndex !== null ? ($messages[$selectedMessageIndex] ?? null) : null; @endphp

                    <div class="grid overflow-hidden rounded-lg border border-zinc-200 lg:h-[calc(100dvh-13rem)] lg:min-h-[40rem] lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)] dark:border-white/10">

                        {{-- Liste des messages (fond doux + cartes) --}}
                        <div class="flex max-h-[70dvh] min-h-0 flex-col border-b border-zinc-200 bg-zinc-400/5 lg:max-h-none lg:border-b-0 lg:border-r dark:border-white/10 dark:bg-zinc-900">
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
                                        $meta       = $this->messageMeta($msg);
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

                        {{-- Panneau de lecture --}}
                        <div class="flex min-h-0 min-w-0 flex-col">
                            @if ($selected)
                                @php
                                    $meta        = $this->messageMeta($selected);
                                    $files       = collect($selected['attachments'] ?? [])->map(fn ($a) => $this->attachmentInfo($a));
                                    $imageFiles  = $files->where('isImage', true)->filter(fn ($f) => $f['url']);
                                    $otherFiles  = $files->where('isImage', false);
                                @endphp

                                {{-- En-tête du message --}}
                                <div class="shrink-0 space-y-4 border-b border-zinc-200 px-6 py-5 dark:border-white/10">
                                    <div class="flex items-start justify-between gap-3">
                                        <flux:heading size="lg" class="break-words">{{ $meta['subject'] }}</flux:heading>

                                        <span class="shrink-0 rounded-full bg-zinc-200/70 px-2.5 py-0.5 text-xs font-medium text-zinc-600 dark:bg-white/10 dark:text-zinc-300">
                                            {{ $selectedMessageIndex + 1 }} / {{ count($messages) }}
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <flux:avatar size="md" :name="$meta['name']" color="auto" :color:seed="$meta['name']" />

                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="truncate font-medium text-zinc-900 dark:text-white" title="{{ $selected['from'] ?? '' }}">{{ $meta['name'] }}</span>

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

                                            <p class="truncate text-sm text-zinc-500 dark:text-zinc-400">{{ __('À') }} : {{ $meta['to'] }}</p>
                                            <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ $meta['date'] ?: '-' }}</p>
                                        </div>
                                    </div>
                                </div>

                                {{-- Corps du message --}}
                                <div class="min-h-0 flex-1 space-y-6 overflow-y-auto px-6 py-5">
                                    <div>
                                        @if ($translatedMessage)
                                            <div class="mb-3 inline-flex items-center gap-2 rounded-full bg-amber-100/80 px-3 py-1 text-xs font-medium text-amber-700 dark:bg-amber-400/15 dark:text-amber-300">
                                                <i class="hgi-stroke hgi-translate"></i>
                                                <span>{{ __('Version traduite') }}</span>
                                            </div>

                                            <div class="break-words rounded-lg bg-zinc-400/5 p-4 text-sm leading-relaxed text-zinc-700 dark:bg-white/5 dark:text-zinc-300">
                                                {!! $translatedMessage !!}
                                            </div>
                                        @else
                                            @php $parts = $this->presentMessage((string) ($selected['message'] ?? '')); @endphp

                                            <div class="break-words rounded-lg bg-zinc-400/5 p-4 text-sm leading-relaxed text-zinc-700 dark:bg-white/5 dark:text-zinc-300">
                                                <div>{!! $parts['body'] !!}</div>

                                                {{-- Signature --}}
                                                @if ($parts['signature'])
                                                    <div class="mt-5 border-l-2 border-zinc-300 pl-3 dark:border-white/20">
                                                        <p class="mb-1 flex items-center gap-1 text-[11px] font-medium uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                                                            <i class="hgi-stroke hgi-signature"></i>
                                                            <span>{{ __('Signature') }}</span>
                                                        </p>
                                                        <div class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">{!! $parts['signature'] !!}</div>
                                                    </div>
                                                @endif

                                                {{-- Historique cité (repliable) --}}
                                                @if ($parts['quoted'])
                                                    <div wire:key="quoted-{{ $selectedMessageIndex }}" x-data="{ open: false }" class="mt-5 border-t border-zinc-200 pt-3 dark:border-white/10">
                                                        <button
                                                            type="button"
                                                            @click="open = ! open"
                                                            class="inline-flex items-center gap-1.5 rounded-md bg-zinc-200/60 px-2 py-0.5 text-xs font-medium text-zinc-600 transition hover:bg-zinc-200 dark:bg-white/10 dark:text-zinc-300 dark:hover:bg-white/15"
                                                        >
                                                            <i class="hgi-stroke hgi-more-horizontal"></i>
                                                            <span x-text="open ? 'Masquer l’historique du message' : 'Afficher l’historique du message'"></span>
                                                        </button>

                                                        <div x-show="open" x-collapse x-cloak class="mt-3 border-l-2 border-sky-300 pl-3 text-xs leading-relaxed text-zinc-500 dark:border-sky-400/40 dark:text-zinc-400">
                                                            {!! $parts['quoted'] !!}
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Pièces jointes --}}
                                    @if ($files->count())
                                        <div class="space-y-3">
                                            <div class="flex items-center gap-2">
                                                <i class="hgi-stroke hgi-attachment-01 text-zinc-500"></i>
                                                <flux:heading>{{ __('Pièces jointes') }}</flux:heading>
                                                <span class="text-sm text-zinc-500">{{ $files->count() }}</span>
                                            </div>

                                            @if ($imageFiles->isNotEmpty())
                                                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                                    @foreach ($imageFiles as $img)
                                                        <a
                                                            href="{{ $img['url'] }}"
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            class="group relative block overflow-hidden rounded-lg shadow-[0_1px_3px_rgba(15,15,15,0.08)] ring-1 ring-black/[0.05] dark:ring-white/10"
                                                        >
                                                            <img src="{{ $img['url'] }}" alt="{{ $img['name'] }}" loading="lazy" class="h-28 w-full object-cover transition duration-300 group-hover:scale-105" />
                                                            <span class="absolute inset-x-0 bottom-0 truncate bg-gradient-to-t from-black/60 to-transparent px-2 pb-1 pt-4 text-xs text-white">{{ $img['name'] }}</span>
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @endif

                                            @foreach ($otherFiles as $file)
                                                <div class="flex items-center justify-between gap-3 rounded-lg bg-white px-3.5 py-3 shadow-[0_1px_3px_rgba(15,15,15,0.08)] ring-1 ring-black/[0.03] dark:bg-zinc-800 dark:ring-white/5">
                                                    <div class="flex min-w-0 items-center gap-3">
                                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-md bg-zinc-100 text-zinc-500 dark:bg-white/10 dark:text-zinc-300">
                                                            <i class="hgi-stroke hgi-file-01" style="width:1.25rem;height:1.25rem;font-size:1.25rem"></i>
                                                        </span>

                                                        <div class="min-w-0">
                                                            <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $file['name'] }}</p>
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
                    </div>

                    {{-- Barre d'actions flottante --}}
                    <div class="pointer-events-none fixed inset-x-0 bottom-6 z-40 flex justify-center px-4">
                        <div class="pointer-events-auto flex flex-wrap items-center justify-center gap-2 rounded-2xl bg-white/90 p-2 shadow-[0_8px_30px_rgba(15,15,15,0.15)] ring-1 ring-black/5 backdrop-blur dark:bg-zinc-800/90 dark:ring-white/10">
                            @if (! empty($details['need_attention']))
                                <flux:button size="sm" variant="filled" wire:click="$set('showReadModal', true)">
                                    <span class="flex items-center gap-2">
                                        <i class="hgi-stroke hgi-tick-02"></i>
                                        <span>{{ __('Marquer comme lu') }}</span>
                                    </span>
                                </flux:button>
                            @endif

                            @if ($selected)
                                @if ($translatedMessage)
                                    <flux:button size="sm" wire:click="showOriginalMessage">
                                        <span class="flex items-center gap-2">
                                            <i class="hgi-stroke hgi-arrow-turn-backward"></i>
                                            <span>{{ __("Revenir à l'original") }}</span>
                                        </span>
                                    </flux:button>
                                @else
                                    <flux:button size="sm" wire:click="translateMessage" wire:loading.attr="disabled" wire:target="translateMessage">
                                        <span class="flex items-center gap-2">
                                            <i class="hgi-stroke hgi-translate"></i>
                                            <span>{{ __('Traduire en français') }}</span>
                                        </span>
                                    </flux:button>
                                @endif
                            @endif

                            @if (count($messages) > 0)
                                <flux:button size="sm" variant="primary" wire:click="replyFirstMessage">
                                    <span class="flex items-center gap-2">
                                        <i class="hgi-stroke hgi-arrow-turn-backward"></i>
                                        <span>{{ __('Répondre') }}</span>
                                    </span>
                                </flux:button>
                            @else
                                <flux:button size="sm" variant="primary" wire:click="writeNewMessage">
                                    <span class="flex items-center gap-2">
                                        <i class="hgi-stroke hgi-sent"></i>
                                        <span>{{ __('Nouveau message') }}</span>
                                    </span>
                                </flux:button>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- ========== Historique d'actions ========== --}}
                @if ($activeTab === 'commentaire')
                    <div class="mx-auto max-w-4xl space-y-6">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <flux:heading size="lg">{{ __("Historique d'actions") }}</flux:heading>
                                <flux:text class="mt-1">{{ __('Suivez toutes les actions et mises à jour effectuées sur ce ticket.') }}</flux:text>
                            </div>
                            <flux:badge color="indigo">
                                <span class="flex items-center gap-1">
                                    <i class="hgi-stroke hgi-clock-01"></i>
                                    <span>{{ count($comments) }}</span>
                                </span>
                            </flux:badge>
                        </div>

                        @if (empty($comments))
                            <div class="rounded-lg border border-dashed border-zinc-300 py-12 text-center dark:border-white/10">
                                <i class="hgi-stroke hgi-clock-01 mx-auto text-zinc-300" style="width:3rem;height:3rem;font-size:3rem"></i>
                                <flux:heading class="mt-3">{{ __('Aucune action') }}</flux:heading>
                                <flux:text class="mt-1">{{ __("Aucune action n'a encore été enregistrée sur ce ticket.") }}</flux:text>
                            </div>
                        @else
                            <ol class="relative space-y-6 border-s border-zinc-200 ps-6 dark:border-white/10">
                                @foreach ($comments as $c)
                                    @php $comment = $this->presentComment($c); @endphp

                                    <li wire:key="comment-{{ $c['id'] ?? $loop->index }}" class="relative">
                                        <span class="absolute -start-[1.85rem] top-1.5 size-2.5 rounded-full ring-4 ring-white dark:ring-zinc-900 {{ $comment['type'] === 'action' ? 'bg-blue-500' : 'bg-zinc-400' }}"></span>

                                        <div class="flex flex-wrap items-center gap-2">
                                            <flux:badge :color="$comment['type'] === 'action' ? 'blue' : 'zinc'" size="sm">
                                                {{ $comment['type'] === 'action' ? __('Action') : ($comment['type'] === 'etat' ? __('État') : ucfirst($comment['type'])) }}
                                            </flux:badge>
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400" title="{{ $comment['ago'] }}">{{ $comment['date'] }}</span>
                                        </div>

                                        <p class="mt-1 whitespace-pre-wrap break-words text-sm text-zinc-800 dark:text-zinc-200">{{ $comment['text'] }}</p>
                                    </li>
                                @endforeach
                            </ol>

                            <div class="flex items-center justify-center gap-1.5 rounded-lg border-2 border-dashed border-zinc-200 p-4 text-center text-sm text-zinc-500 dark:border-white/10">
                                <i class="hgi-stroke hgi-checkmark-circle-02 text-green-500"></i>
                                <span>{{ __("Vous êtes à jour avec l'historique du ticket") }}</span>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- Assets FilePond (chargés une seule fois) --}}
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

            /* ---- Animation d'ouverture du drawer d'envoi mail ---- */
            @keyframes compose-fade   { from { opacity: 0; } to { opacity: 1; } }
            @keyframes compose-rise   { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
            @keyframes compose-drop   { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
            @keyframes compose-slide  { from { opacity: 0; transform: translateX(28px); } to { opacity: 1; transform: translateX(0); } }

            .compose-drawer[open]::backdrop { animation: compose-fade .35s ease-out both; backdrop-filter: blur(2px); }

            .compose-drawer[open] .compose-head   { animation: compose-drop .4s cubic-bezier(.22, 1, .36, 1) .12s both; }
            .compose-drawer[open] .compose-body > * { animation: compose-slide .45s cubic-bezier(.22, 1, .36, 1) both; }
            .compose-drawer[open] .compose-body > :nth-child(1) { animation-delay: .16s; }
            .compose-drawer[open] .compose-body > :nth-child(2) { animation-delay: .21s; }
            .compose-drawer[open] .compose-body > :nth-child(3) { animation-delay: .26s; }
            .compose-drawer[open] .compose-body > :nth-child(4) { animation-delay: .31s; }
            .compose-drawer[open] .compose-body > :nth-child(5) { animation-delay: .36s; }
            .compose-drawer[open] .compose-body > :nth-child(6) { animation-delay: .41s; }
            .compose-drawer[open] .compose-foot   { animation: compose-rise .45s cubic-bezier(.22, 1, .36, 1) .3s both; }

            @media (prefers-reduced-motion: reduce) {
                .compose-drawer[open]::backdrop,
                .compose-drawer[open] .compose-head,
                .compose-drawer[open] .compose-body > *,
                .compose-drawer[open] .compose-foot { animation: none; }
            }
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
        <flux:modal wire:model.self="showComposeDrawer" variant="flyout" position="right" class="compose-drawer w-full max-w-full p-0 md:w-[64rem]">
            <div class="flex h-full flex-col">

                {{-- En-tête --}}
                <div class="compose-head shrink-0 border-b border-zinc-200 px-6 py-5 pe-14 dark:border-white/10">
                    <flux:heading size="lg">{{ __("Répondre à l'email") }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ __('Ticket') }} {{ $details['num_ticket'] ?? $ticketId }}
                        @if (! empty($details['nom_client'])) · {{ $details['nom_client'] }} @endif
                        · {{ count($messages) }} {{ __('message(s)') }}
                    </flux:text>
                </div>

                {{-- Corps défilant --}}
                <div class="compose-body min-h-0 flex-1 overflow-y-auto">

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
                <div class="compose-foot flex shrink-0 flex-wrap items-center gap-2 border-t border-zinc-200 bg-white px-6 py-4 dark:border-white/10 dark:bg-zinc-800">
                    <flux:modal.close>
                        <flux:button variant="ghost" size="sm">
                            <span class="flex items-center gap-2">
                                <i class="hgi-stroke hgi-cancel-01"></i>
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

        {{-- Modale : marquer comme lu --}}
        <flux:modal wire:model.self="showReadModal" class="md:w-96">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __("Confirmation de l'action") }}</flux:heading>
                    <flux:text class="mt-2">
                        {{ __('Êtes-vous sûr de vouloir marquer les messages comme lus ? Cette opération est irréversible.') }}
                    </flux:text>
                </div>

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close><flux:button variant="ghost">{{ __('Annuler') }}</flux:button></flux:modal.close>
                    <flux:button variant="danger" wire:click="confirmerActionLu">{{ __('Confirmer') }}</flux:button>
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
    @endif
</div>
