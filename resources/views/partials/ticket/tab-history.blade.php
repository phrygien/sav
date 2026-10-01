{{-- Onglet « Historique d'actions ». Attend : $comments --}}
@use('App\Support\MailText')

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
                @php $comment = MailText::presentComment($c); @endphp

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
