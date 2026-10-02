<div class="mx-auto w-full" @if (! $loaded) wire:init="load" @endif>
    <flux:heading size="xl">Tickets redondants</flux:heading>

    <flux:text class="mt-2">
        Expéditeurs ayant généré plusieurs tickets similaires.
        @if ($loaded)
            ({{ number_format($this->tickets->total(), 0, ',', ' ') }} groupes)
        @else
            <span class="inline-block h-4 w-20 animate-pulse rounded bg-zinc-200 align-middle dark:bg-zinc-700"></span>
        @endif
    </flux:text>

    <div class="mt-5 flex flex-wrap items-center gap-3">
        <flux:select wire:model.live="ticketStatus" class="max-w-48">
            @foreach ($this::STATUSES as $value => $label)
                <flux:select.option :value="$value">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="dateRange" class="max-w-48">
            @foreach ($this::RANGES as $value => $label)
                <flux:select.option :value="$value">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="ms-auto flex items-center gap-2">
            {{-- Les données sont gardées 1 min côté serveur : ce bouton force une mise à jour --}}
            @if ($loaded)
                <flux:button
                    size="sm"
                    variant="ghost"
                    wire:click="refresh"
                    wire:loading.attr="disabled"
                    wire:target="refresh"
                    aria-label="Actualiser"
                >
                    <i class="hgi-stroke hgi-refresh" wire:loading.remove wire:target="refresh"></i>
                    <i class="hgi-stroke hgi-loading-03 animate-spin" wire:loading wire:target="refresh"></i>
                </flux:button>
            @endif

            <flux:text class="whitespace-nowrap">Par page</flux:text>
            <flux:select wire:model.live="perPage" class="w-24">
                @foreach ($this::PER_PAGES as $n)
                    <flux:select.option :value="$n">{{ $n }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    @if ($loaded && $error)
        <flux:callout variant="danger" icon="exclamation-triangle" class="mt-5" :heading="$error">
            <x-slot name="actions">
                <flux:button size="sm" wire:click="refresh">Réessayer</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    @if (! $loaded)
        {{-- Squelette pendant le chargement initial --}}
        <flux:card class="mt-5">
            <div class="animate-pulse space-y-3">
                <div class="h-8 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                @foreach (range(1, 6) as $i)
                    <div wire:key="sk-red-{{ $i }}" class="flex items-center gap-4">
                        <div class="size-5 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                        <div class="h-4 w-48 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                        <div class="h-5 w-20 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                        <div class="h-5 w-8 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                        <div class="h-4 flex-1 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @else
        <flux:card
            class="mt-5 transition-opacity"
            wire:loading.class="opacity-60"
            wire:target="ticketStatus,dateRange,perPage,refresh,gotoPage,nextPage,previousPage,setPage"
        >
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
                        @php $subjects = $group['subjects']; @endphp

                        {{-- open: true = détails affichés par défaut --}}
                        <flux:table.row
                            :key="$group['mail'].'|'.$group['order']"
                            x-data="{ open: true }"
                        >
                            <flux:table.cell class="align-top">
                                <button
                                    type="button"
                                    x-on:click="open = !open"
                                    x-bind:aria-expanded="open"
                                    class="rounded p-1 hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                    aria-label="Afficher / masquer les sujets"
                                >
                                    <flux:icon.chevron-down
                                        class="size-4 transition-transform duration-200"
                                        x-bind:class="{ '-rotate-90': !open }"
                                    />
                                </button>
                            </flux:table.cell>

                            <flux:table.cell variant="strong" class="whitespace-nowrap align-top">
                                {{ $group['mail'] }}
                            </flux:table.cell>

                            <flux:table.cell class="align-top">
                                <flux:badge size="sm" :color="$group['order'] === 'inconnu' ? 'zinc' : 'green'">
                                    {{ $group['order'] }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell align="end" class="align-top">
                                <flux:badge size="sm" color="red">{{ $group['count'] }}</flux:badge>
                            </flux:table.cell>

                            <flux:table.cell class="max-w-xl whitespace-normal align-top">
                                {{-- Replié : premier sujet + compteur --}}
                                <div x-show="!open" class="flex items-center gap-2">
                                    <span class="truncate">{{ $subjects[0] ?? '' }}</span>
                                    @if (count($subjects) > 1)
                                        <flux:badge size="sm" color="zinc">+{{ count($subjects) - 1 }}</flux:badge>
                                    @endif
                                </div>

                                {{-- Déplié : un sujet par ligne --}}
                                <div x-show="open" x-cloak>
                                    <flux:text size="sm" class="mb-1 text-zinc-500">
                                        {{ count($subjects) }} sujet(s) distinct(s)
                                    </flux:text>

                                    <ul class="max-h-64 divide-y divide-zinc-100 overflow-y-auto rounded-md border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
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
    @endif
</div>
