<?php

use App\Services\CosmiaApi;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public bool $ready = false;

    /** @var array<int, array{user_id:int,name:string,en_attente:int,en_cours:int,cloture:int,total:int}> */
    public array $rows = [];

    /** @var array{en_attente:int,en_cours:int,cloture:int,total:int} */
    public array $unassigned = ['en_attente' => 0, 'en_cours' => 0, 'cloture' => 0, 'total' => 0];

    public ?string $error = null;

    /** Utilisateur actuellement utilisé comme filtre sur le kanban */
    public ?int $selectedUserId = null;

    public function loadData(): void
    {
        $this->error = null;

        try {
            $data = app(CosmiaApi::class)->get('ticket/getTicketDistributionSummary/list');
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();
            $this->ready = true;

            return;
        }

        $this->rows = collect($data['ticket_user'] ?? [])
            ->map(fn ($r) => $this->normalize($r) + [
                    'user_id' => (int) ($r['user_id'] ?? 0),
                    'name'    => (string) ($r['name'] ?? '—'),
                ])
            ->sortByDesc('total')
            ->values()
            ->all();

        $this->unassigned = $this->normalize($data['ticket_not_assigned'][0] ?? []);
        $this->ready = true;
    }

    #[On('tickets-changed')]
    public function refresh(): void
    {
        $this->loadData();
    }

    /** Clic sur une carte : active le filtre, ou le retire si on reclique dessus. */
    public function selectUser(int $userId): void
    {
        $this->selectedUserId = $this->selectedUserId === $userId ? null : $userId;

        $this->dispatch('user-filter-changed', userId: $this->selectedUserId);
    }

    /** Le kanban a changé de filtre de son côté (ex. « Ticket qui m'est assigné »). */
    #[On('user-filter-sync')]
    public function syncSelection(?int $userId = null): void
    {
        $this->selectedUserId = $userId;
    }

    /** L'API renvoie des chaînes ("4") : on caste et on calcule le total. */
    private function normalize(array $r): array
    {
        $a = (int) ($r['en_attente'] ?? 0);
        $c = (int) ($r['en_cours'] ?? 0);
        $d = (int) ($r['cloture'] ?? 0);

        return ['en_attente' => $a, 'en_cours' => $c, 'cloture' => $d, 'total' => $a + $c + $d];
    }

    public function colorForName(string $name): string
    {
        $colors = ['red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal', 'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia', 'pink', 'rose'];

        return $colors[crc32($name) % count($colors)];
    }

    public function with(): array
    {
        return [
            'totals' => [
                'en_attente' => collect($this->rows)->sum('en_attente') + $this->unassigned['en_attente'],
                'en_cours'   => collect($this->rows)->sum('en_cours')   + $this->unassigned['en_cours'],
                'cloture'    => collect($this->rows)->sum('cloture')    + $this->unassigned['cloture'],
            ],
        ];
    }
};
?>

<div wire:init="loadData">
    @php
        $cols = [
            'en_attente' => ['label' => __('En attente'), 'accent' => 'text-orange-600 dark:text-orange-400',   'dot' => 'bg-orange-400'],
            'en_cours'   => ['label' => __('En cours'),   'accent' => 'text-sky-600 dark:text-sky-400',         'dot' => 'bg-sky-400'],
            'cloture'    => ['label' => __('Clôturé'),    'accent' => 'text-emerald-600 dark:text-emerald-400', 'dot' => 'bg-emerald-400'],
        ];
    @endphp

    <div
        x-data="{ open: $persist(true).as('distribution-open') }"
        class="mb-4 rounded-2xl border border-zinc-200/70 bg-zinc-50 px-[3px] pb-[3px] dark:border-zinc-700/50 dark:bg-zinc-900/60"
    >
        {{-- En-tête compact : titre + totaux par statut + actions --}}
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-2.5">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                <div class="text-xs font-semibold uppercase tracking-wide text-purple-600 dark:text-purple-400">
                    {{ __('Répartition des tickets') }}
                </div>

                @if ($ready && ! $error)
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                        @foreach ($cols as $key => $c)
                            <span class="inline-flex items-center gap-1.5 {{ $c['accent'] }}">
                                <span class="size-1.5 rounded-full {{ $c['dot'] }}"></span>
                                <span class="font-semibold tabular-nums">{{ $totals[$key] }}</span>
                                <span class="text-zinc-500 dark:text-zinc-400">{{ $c['label'] }}</span>
                            </span>
                        @endforeach
                        <span class="text-zinc-500 dark:text-zinc-400">
                            · <span class="font-semibold tabular-nums text-zinc-700 dark:text-zinc-200">{{ array_sum($totals) }}</span> {{ __('au total') }}
                        </span>
                    </div>
                @elseif (! $ready)
                    <div class="h-3 w-48 animate-pulse rounded bg-zinc-200 dark:bg-zinc-700"></div>
                @endif
            </div>

            <div class="flex items-center gap-1.5">
                @if ($selectedUserId !== null)
                    <flux:button
                        wire:click="selectUser({{ $selectedUserId }})"
                        size="xs"
                        variant="outline"
                        icon="x-mark"
                    >
                        {{ __('Retirer le filtre') }}
                    </flux:button>
                @endif

                <flux:button
                    wire:click="refresh"
                    wire:loading.attr="disabled"
                    wire:target="refresh,loadData"
                    size="xs"
                    variant="outline"
                    icon="arrow-path"
                    aria-label="{{ __('Actualiser') }}"
                />

                {{-- Bouton replier/déplier : deux icônes, Alpine affiche la bonne --}}
                <flux:button
                    x-on:click="open = ! open"
                    size="xs"
                    variant="outline"
                    aria-label="{{ __('Replier') }}"
                >
                    <flux:icon.chevron-up x-show="open" x-cloak variant="micro" />
                    <flux:icon.chevron-down x-show="! open" x-cloak variant="micro" />
                </flux:button>
            </div>
        </div>

        {{-- Contenu repliable --}}
        <div x-show="open" x-collapse>
            <div
                class="rounded-xl border border-zinc-300/70 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-800"
                wire:loading.class="opacity-60"
                wire:target="refresh"
            >
                @if (! $ready)
                    <div class="grid animate-pulse grid-cols-[repeat(auto-fill,minmax(12rem,1fr))] gap-2">
                        @foreach (range(1, 6) as $i)
                            <div class="h-12 rounded-lg bg-zinc-100 dark:bg-zinc-700"></div>
                        @endforeach
                    </div>
                @elseif ($error)
                    <flux:callout variant="danger" icon="exclamation-circle" :heading="$error" />
                @else
                    <div class="grid grid-cols-[repeat(auto-fill,minmax(12rem,1fr))] gap-2">
                        @foreach ($rows as $row)
                            @php $active = $selectedUserId === $row['user_id']; @endphp

                            <button
                                type="button"
                                wire:key="dist-{{ $row['user_id'] }}"
                                wire:click="selectUser({{ $row['user_id'] }})"
                                aria-pressed="{{ $active ? 'true' : 'false' }}"
                                title="{{ $active ? __('Retirer le filtre') : __('Filtrer les tickets de :name', ['name' => $row['name']]) }}"
                                class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg border px-2.5 py-2 text-left transition
                                    {{ $active
                                        ? 'border-purple-400 bg-purple-50 ring-2 ring-purple-400/40 dark:border-purple-500 dark:bg-purple-500/10'
                                        : 'border-zinc-200/80 bg-zinc-50/60 hover:border-zinc-400/70 hover:bg-white dark:border-zinc-700 dark:bg-zinc-900/40 dark:hover:border-zinc-500 dark:hover:bg-zinc-800' }}"
                            >
                                <flux:avatar size="xs" :name="$row['name']" :color="$this->colorForName($row['name'])" />

                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-xs font-medium text-zinc-900 dark:text-white">{{ $row['name'] }}</div>

                                    <div class="mt-0.5 flex items-center gap-2 text-[11px] tabular-nums">
                                        @foreach ($cols as $key => $c)
                                            <span
                                                class="inline-flex items-center gap-1 {{ $row[$key] > 0 ? $c['accent'].' font-semibold' : 'text-zinc-300 dark:text-zinc-600' }}"
                                                title="{{ $c['label'] }}"
                                            >
                                                <span class="size-1.5 rounded-full {{ $row[$key] > 0 ? $c['dot'] : 'bg-zinc-200 dark:bg-zinc-700' }}"></span>{{ $row[$key] }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="text-sm font-bold tabular-nums text-zinc-900 dark:text-white" title="{{ __('Total') }}">{{ $row['total'] }}</div>
                            </button>
                        @endforeach

                        {{-- Non assignés (non cliquable) --}}
                        <div class="flex items-center gap-2.5 rounded-lg border border-dashed border-zinc-300 px-2.5 py-2 dark:border-zinc-600">
                            <flux:avatar size="xs" icon="user-minus" />

                            <div class="min-w-0 flex-1">
                                <div class="truncate text-xs italic text-zinc-500 dark:text-zinc-400">{{ __('Non assignés') }}</div>

                                <div class="mt-0.5 flex items-center gap-2 text-[11px] tabular-nums">
                                    @foreach ($cols as $key => $c)
                                        <span
                                            class="inline-flex items-center gap-1 {{ $unassigned[$key] > 0 ? $c['accent'].' font-semibold' : 'text-zinc-300 dark:text-zinc-600' }}"
                                            title="{{ $c['label'] }}"
                                        >
                                            <span class="size-1.5 rounded-full {{ $unassigned[$key] > 0 ? $c['dot'] : 'bg-zinc-200 dark:bg-zinc-700' }}"></span>{{ $unassigned[$key] }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>

                            <div class="text-sm font-bold tabular-nums text-zinc-900 dark:text-white">{{ $unassigned['total'] }}</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
