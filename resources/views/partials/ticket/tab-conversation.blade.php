{{-- Onglet « Conversation » : liste à gauche, panneau de lecture à droite.
     Attend : $messages, $metas, $details, $selectedMessageIndex --}}
@php $selected = $selectedMessageIndex !== null ? ($messages[$selectedMessageIndex] ?? null) : null; @endphp

<div class="grid gap-6 lg:h-[calc(100dvh-13rem)] lg:min-h-[40rem] lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
    @include('partials.ticket.message-list')
    @include('partials.ticket.message-reader')
</div>
