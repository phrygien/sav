<?php

use App\Livewire\Concerns\NotifiesWithToast;
use App\Services\CosmiaApi;
use App\Support\MailText;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Mews\Purifier\Facades\Purifier;

/**
 * Page détail d'un ticket : /kanban/details?ticket=12423
 *
 * Responsabilités de CE composant : chargement du ticket, onglets, statut, note, lecture des mails.
 * - Chatbot            -> <livewire:ticket.chatbot>
 * - Rédaction / envoi  -> <livewire:ticket.compose-drawer>
 * - Formatage de texte -> App\Support\MailText
 * - Vues               -> resources/views/partials/ticket/*
 */
new class extends Component
{
    use NotifiesWithToast;

    public const STATUSES = [
        'en attente' => ['title' => 'En attente', 'color' => 'amber'],
        'en cours'   => ['title' => 'En cours',   'color' => 'blue'],
        'cloture'    => ['title' => 'Clôturé',    'color' => 'green'],
    ];

    #[Url]
    public string $ticket = '';

    // Verrouillé : sinon un utilisateur pourrait modifier l'id depuis le navigateur
    #[Locked]
    public int $ticketId = 0;

    #[Locked]
    public array $ticketDetails = [];

    /** false tant que le ticket n'a pas été chargé (rendu initial = squelettes) */
    public bool $loaded = false;

    public ?string $error = null;

    public string $activeTab = 'description';

    public bool $showReadModal = false;

    // Lecture des mails
    public ?int $selectedMessageIndex = null;

    public string $translatedMessage = '';

    // Note du ticket (HTML, édité avec Jodit)
    public string $note = '';

    /** true si une note existe déjà côté API : addNote() fera alors un PUT au lieu d'un POST */
    #[Locked]
    public bool $hasNote = false;

    /* ------------------------------------------------------------------ */
    /*  Cycle de vie                                                       */
    /* ------------------------------------------------------------------ */

    public function mount(): void
    {
        if ($this->ticket === '' || ! ctype_digit($this->ticket)) {
            $this->redirectRoute('kanban', navigate: true);

            return;
        }

        $this->ticketId = (int) $this->ticket;
    }

    /** Chargement différé (wire:init) */
    public function loadTicket(): void
    {
        if ($this->loaded || $this->ticketId === 0) {
            return;
        }

        $this->fetchTicketDetails();
        $this->fetchNote();
        $this->loaded = true;
    }

    /** Émis par le tiroir d'envoi une fois le mail parti */
    #[On('ticket-updated')]
    public function refresh(): void
    {
        // La note n'est pas rechargée ici : on n'écrase pas une saisie en cours
        $this->fetchTicketDetails();
    }

    /* ------------------------------------------------------------------ */
    /*  Computed                                                           */
    /* ------------------------------------------------------------------ */

    #[Computed]
    public function chatId(): ?string
    {
        $id = $this->ticketDetails['details'][0]['conversation_chat_id'] ?? null;

        return filled($id) ? (string) $id : null;
    }

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

        if ($this->chatId) {
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
            'en attente' => ['label' => 'Mettre en cours',       'next' => 'en cours'],
            'en cours'   => ['label' => 'Clôturer le ticket',    'next' => 'cloture'],
            'cloture'    => ['label' => 'Réouvrir (en attente)', 'next' => 'en attente'],
            default      => ['label' => 'Mettre en attente',     'next' => 'en attente'],
        };
    }

    /** Métadonnées de tous les messages, calculées une seule fois par requête */
    #[Computed]
    public function metas(): array
    {
        $detail  = $this->ticketDetails['details'][0] ?? [];
        $client  = (string) ($detail['original_client_mail'] ?? '');
        $support = (string) ($detail['reception_mail'] ?? '');

        return array_map(
            fn ($msg) => MailText::messageMeta($msg, $client, $support),
            $this->ticketDetails['conversation']['messages'] ?? []
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Chargement                                                         */
    /* ------------------------------------------------------------------ */

    private function fetchTicketDetails(): void
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

        if (empty($data['details'][0])) {
            $this->error = __('Ticket introuvable.');

            return;
        }

        // Champs texte du ticket : entités HTML / mauvais encodage corrigés une fois pour toutes
        foreach (['subject_ticket', 'nom_client', 'num_commande', 'num_ticket', 'reception_mail', 'original_client_mail', 'to_do'] as $key) {
            if (isset($data['details'][0][$key]) && is_string($data['details'][0][$key])) {
                $data['details'][0][$key] = MailText::decode($data['details'][0][$key]);
            }
        }

        $this->error         = null;
        $this->ticketDetails = $data;

        // Les données ont changé : on invalide les propriétés calculées déjà mémorisées
        unset($this->tabs, $this->nextStatus, $this->metas, $this->chatId);
    }

    /* ------------------------------------------------------------------ */
    /*  Note                                                               */
    /* ------------------------------------------------------------------ */

    /** GET /ticket/getNote/{id} : remplit l'éditeur avec la note existante */
    private function fetchNote(): void
    {
        try {
            $res = app(CosmiaApi::class)->get("/ticket/getNote/{$this->ticketId}");
        } catch (\RuntimeException $e) {
            // Pas de note existante ou API indisponible : non bloquant, mais on garde une trace
            Log::warning('getNote KO', ['ticket' => $this->ticketId, 'error' => $e->getMessage()]);
            $this->hasNote = false;

            return;
        }

        $this->hasNote = ! empty($res['note']['id']);

        $raw = $res['note']['note'] ?? '';

        $this->note = is_string($raw) ? $this->normalizeNote($raw) : '';
    }

    /** L'API renvoie le champ "note" sous forme de JSON encodé : on en extrait le HTML */
    private function normalizeNote(string $raw): string
    {
        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            // "test_note" : uniquement pour la note de test actuelle, à retirer ensuite
            return (string) ($decoded['content'] ?? $decoded['test_note'] ?? '');
        }

        return $raw;
    }

    /** POST /ticket/addNote (création) ou PUT /ticket/updateNote/{ticket_id} (modification) */
    public function addNote(): void
    {
        $clean = Purifier::clean($this->note);

        if (trim(html_entity_decode(strip_tags($clean))) === '') {
            $this->addError('note', __('La note est vide.'));

            return;
        }

        $method = $this->hasNote ? 'PUT' : 'POST';

        try {
            $res = $this->hasNote
                ? app(CosmiaApi::class)->put("/ticket/updateNote/{$this->ticketId}", [
                    'note' => ['content' => $clean],
                ])
                : app(CosmiaApi::class)->post('/ticket/addNote', [
                    'ticket_id' => $this->ticketId,
                    'note'      => ['content' => $clean],
                ]);
        } catch (\RuntimeException $e) {
            Log::error('addNote KO', ['method' => $method, 'ticket' => $this->ticketId, 'error' => $e->getMessage()]);
            $this->notify(__("Impossible d'enregistrer la note !"), 'danger');

            return;
        }

        // Trace temporaire : permet de voir quelle méthode part et ce que l'API répond
        Log::info('addNote OK', ['method' => $method, 'ticket' => $this->ticketId, 'response' => $res]);

        // DEBUG TEMPORAIRE : à supprimer une fois le problème trouvé
        $this->notify("DEBUG {$method} → ".json_encode($res, JSON_UNESCAPED_UNICODE));

        // On relit la note côté API : l'éditeur affiche ce qui est réellement enregistré
        $this->fetchNote();

        // DEBUG TEMPORAIRE
        Log::info('getNote après save', ['hasNote' => $this->hasNote, 'note' => $this->note]);

        $this->resetErrorBag('note');

        $saved = trim(html_entity_decode(strip_tags($this->note)));
        $sent  = trim(html_entity_decode(strip_tags($clean)));

        if ($saved !== $sent) {
            $this->notify(__("L'API n'a pas enregistré la modification."), 'danger');

            return;
        }

        $this->notify(__('Note enregistrée'));
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

    // Select mobile (wire:model.live="activeTab")
    public function updatedActiveTab(string $tab): void
    {
        if (! array_key_exists($tab, $this->tabs)) {
            $this->activeTab = 'description';
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
        $this->fetchTicketDetails();
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
        $this->fetchTicketDetails();
    }

    /* ------------------------------------------------------------------ */
    /*  Lecture des mails                                                  */
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
            $this->translatedMessage = MailText::formatMessage((string) ($res['translated_text'] ?? __('Erreur de traduction')));
        } catch (\RuntimeException) {
            $this->translatedMessage = __("Erreur lors de l'appel API");
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Ouverture du tiroir d'envoi                                        */
    /* ------------------------------------------------------------------ */

    // Appelable depuis cette page (wire:click="openCompose") ou depuis le chatbot (event compose-request)
    #[On('compose-request')]
    public function openCompose(): void
    {
        $detail   = $this->ticketDetails['details'][0] ?? [];
        $messages = $this->ticketDetails['conversation']['messages'] ?? [];
        $first    = $messages[0] ?? null;

        $firstSubject = trim(MailText::decode($first['subject'] ?? ''));

        $this->dispatch('compose-open', context: [
            'ticket_id'        => $this->ticketId,
            'num_ticket'       => $detail['num_ticket'] ?? $this->ticketId,
            'client_name'      => $detail['nom_client'] ?? '',
            'client_mail'      => trim((string) ($detail['original_client_mail'] ?? '')),
            'subject'          => $firstSubject !== '' ? $firstSubject : trim(MailText::decode($detail['subject_ticket'] ?? '')),
            'first_message_id' => $first['message_id'] ?? null,
            'messages_count'   => count($messages),
            'client_message'   => $first
                ? trim(preg_replace('/\n{3,}/', "\n\n", MailText::plainText((string) ($first['message'] ?? ''))) ?? '')
                : '',
        ]);
    }
};
?>
<div class="space-y-6" wire:init="loadTicket">
    @if ($error)
        <flux:callout variant="danger" icon="exclamation-circle" :heading="$error" />
    @elseif (! $loaded)
        @include('partials.ticket.skeleton')
    @else
        {{-- Variables partagées avec tous les partials ci-dessous (@include hérite du scope) --}}
        @php
            $details  = $ticketDetails['details'][0] ?? [];
            $messages = $ticketDetails['conversation']['messages'] ?? [];
            $comments = $ticketDetails['comment'] ?? [];
            $tabs     = $this->tabs;
            $metas    = $this->metas;
        @endphp

        {{-- Mobile : select au-dessus / Desktop : onglets à gauche, contenu à droite --}}
        <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
            @include('partials.ticket.tabs-nav')

            <div class="min-w-0 flex-1">
                @includeWhen($activeTab === 'description', 'partials.ticket.tab-description')

                {{-- Le chatbot est un composant à part : il ne charge ses messages qu'à l'ouverture de l'onglet --}}
                @if ($activeTab === 'chatbot' && $this->chatId)
                    <livewire:ticket.chatbot
                        :conversation-chat-id="$this->chatId"
                        :key="'chatbot-'.$ticketId.'-'.$this->chatId"
                    />
                @endif

                @includeWhen($activeTab === 'conversation', 'partials.ticket.tab-conversation')
                @includeWhen($activeTab === 'commentaire', 'partials.ticket.tab-history')
            </div>
        </div>

        @include('partials.ticket.modal-read')
    @endif

    {{-- Hors des conditions : les assets FilePond se chargent pendant l'appel API --}}
    @if ($ticketId)
        <livewire:ticket.compose-drawer :ticket-id="$ticketId" />
    @endif
</div>
