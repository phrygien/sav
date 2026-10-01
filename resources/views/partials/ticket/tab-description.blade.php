{{-- Onglet « Ticket ». Attend : $details --}}
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
