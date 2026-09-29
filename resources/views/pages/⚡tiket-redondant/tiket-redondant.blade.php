<div class="w-full mx-auto">
    <flux:heading size="xl">Tickets redondants</flux:heading>
    <flux:text class="mt-2">
        Expéditeurs ayant généré plusieurs tickets similaires.
        ({{ number_format($this->tickets->total(), 0, ',', ' ') }} groupes)
    </flux:text>

    <div class="mt-5 flex flex-wrap items-center gap-3">
        <flux:select wire:model.live="ticketStatus" class="max-w-48">
            <flux:select.option value="all">Tous les statuts</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="dateRange" class="max-w-48">
            <flux:select.option value="1">Période : 1</flux:select.option>
        </flux:select>

        <div class="ms-auto flex items-center gap-2">
            <flux:text class="whitespace-nowrap">Par page</flux:text>
            <flux:select wire:model.live="perPage" class="w-24">
                <flux:select.option value="10">10</flux:select.option>
                <flux:select.option value="25">25</flux:select.option>
                <flux:select.option value="50">50</flux:select.option>
                <flux:select.option value="100">100</flux:select.option>
            </flux:select>
        </div>
    </div>

    @if ($error)
        <flux:callout variant="danger" icon="exclamation-triangle" class="mt-5" :heading="$error" />
    @endif

    <flux:card class="mt-5">
        <flux:table :paginate="$this->tickets">
            <flux:table.columns>
                <flux:table.column class="w-10"></flux:table.column>
                <flux:table.column>Expéditeur</flux:table.column>
                <flux:table.column>N° commande</flux:table.column>
                <flux:table.column align="end">Tickets</flux:table.column>
                <flux:table.column>Sujets</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->tickets as $group)
                    @php
                        $subjects = collect(explode(',', html_entity_decode($group['subjects_ticket'] ?? '')))
                            ->map(fn ($s) => trim(preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $s)))
                            ->filter()
                            ->unique()
                            ->values();
                    @endphp

                    {{-- open: true = détails affichés par défaut --}}
                    <flux:table.row
                        :key="$group['original_client_mail']"
                        x-data="{ open: true }"
                    >
                        <flux:table.cell class="align-top">
                            <button
                                type="button"
                                x-on:click="open = !open"
                                x-bind:aria-expanded="open"
                                class="p-1 rounded hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                aria-label="Afficher / masquer les sujets"
                            >
                                <flux:icon.chevron-down
                                    class="size-4 transition-transform duration-200"
                                    x-bind:class="{ '-rotate-90': !open }"
                                />
                            </button>
                        </flux:table.cell>

                        <flux:table.cell variant="strong" class="whitespace-nowrap align-top">
                            {{ $group['original_client_mail'] }}
                        </flux:table.cell>

                        <flux:table.cell class="align-top">
                            <flux:badge size="sm" :color="$group['num_commande'] === 'inconnu' ? 'zinc' : 'green'">
                                {{ $group['num_commande'] }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell align="end" class="align-top">
                            <flux:badge size="sm" color="red">{{ $group['total_in_group'] }}</flux:badge>
                        </flux:table.cell>

                        <flux:table.cell class="max-w-xl align-top whitespace-normal">
                            {{-- Replié : premier sujet + compteur --}}
                            <div x-show="!open" class="flex items-center gap-2">
                                <span class="truncate">{{ $subjects->first() }}</span>
                                @if ($subjects->count() > 1)
                                    <flux:badge size="sm" color="zinc">+{{ $subjects->count() - 1 }}</flux:badge>
                                @endif
                            </div>

                            {{-- Déplié : un sujet par ligne --}}
                            <div x-show="open" x-cloak>
                                <flux:text size="sm" class="mb-1 text-zinc-500">
                                    {{ $subjects->count() }} sujet(s) distinct(s)
                                </flux:text>

                                <ul class="max-h-64 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-700 rounded-md border border-zinc-200 dark:border-zinc-700">
                                    @foreach ($subjects as $subject)
                                        <li class="px-3 py-1.5 text-xs">{{ $subject }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">Aucun ticket redondant.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
