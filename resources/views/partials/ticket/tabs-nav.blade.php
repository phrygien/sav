{{-- Attend : $tabs, $activeTab --}}
{{-- Chaque tab : ['label' => '', 'icon' => '', 'badge' => (opt.), 'badge_tone' => 'alert' (opt.)] --}}

@php
    // Thème centralisé : seul endroit à modifier pour re-thémer la nav
    $ui = [
        'active'      => 'bg-zinc-100 text-zinc-900 dark:bg-white/10 dark:text-white',
        'idle'        => 'text-zinc-600 hover:bg-zinc-50 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/5 dark:hover:text-zinc-100',
        'iconActive'  => 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900',
        'iconIdle'    => 'bg-zinc-100 text-zinc-500 group-hover:bg-zinc-200/70 group-hover:text-zinc-700 dark:bg-white/5 dark:text-zinc-400 dark:group-hover:bg-white/10 dark:group-hover:text-zinc-200',
        'badgeActive' => 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900',
        'badgeIdle'   => 'bg-zinc-100 text-zinc-600 dark:bg-white/10 dark:text-zinc-300',
        'badgeAlert'  => 'bg-red-500 text-white',
        'focus'       => 'focus-visible:ring-2 focus-visible:ring-zinc-900/25 dark:focus-visible:ring-white/30',
    ];
@endphp

<div x-data="{ active: @js($activeTab) }" x-effect="active = $wire.activeTab">

    {{-- Mobile : liste déroulante --}}
    <div class="relative sm:hidden">
        <select
            wire:model.live="activeTab"
            aria-label="Sélectionner un onglet"
            class="w-full appearance-none rounded-lg border border-zinc-200 bg-white py-2.5 pl-3 pr-9 text-[13px] font-medium text-zinc-900 outline-none transition focus:border-zinc-400 focus:ring-2 focus:ring-zinc-900/10 dark:border-white/10 dark:bg-zinc-900 dark:text-white dark:focus:border-white/30 dark:focus:ring-white/10"
        >
            @foreach ($tabs as $key => $tab)
                <option value="{{ $key }}">
                    {{ $tab['label'] }}{{ isset($tab['badge']) ? ' ('.$tab['badge'].')' : '' }}
                </option>
            @endforeach
        </select>
        <i class="hgi-stroke hgi-arrow-down-01 pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-base text-zinc-400" aria-hidden="true"></i>
    </div>

    {{-- Desktop : onglets verticaux (sans conteneur) --}}
    <nav class="hidden sm:sticky sm:top-4 sm:block sm:w-52 sm:shrink-0 lg:w-60" aria-label="Onglets">
        <div
            role="tablist"
            aria-orientation="vertical"
            x-on:keydown.arrow-down.prevent="$focus.wrap().next()"
            x-on:keydown.arrow-up.prevent="$focus.wrap().previous()"
            class="flex flex-col gap-1"
        >
            @foreach ($tabs as $key => $tab)
                <button
                    type="button"
                    role="tab"
                    wire:key="tab-{{ $key }}"
                    wire:click="setTab('{{ $key }}')"
                    x-on:click="active = '{{ $key }}'"
                    :aria-selected="active === '{{ $key }}'"
                    :aria-current="active === '{{ $key }}' ? 'page' : null"
                    :class="active === '{{ $key }}' ? '{{ $ui['active'] }}' : '{{ $ui['idle'] }}'"
                    class="group flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-[13px] font-medium outline-none transition-all duration-150 active:scale-[0.99] {{ $ui['focus'] }}"
                >
                    {{-- Icône / loader --}}
                    <span
                        :class="active === '{{ $key }}' ? '{{ $ui['iconActive'] }}' : '{{ $ui['iconIdle'] }}'"
                        class="flex size-7 shrink-0 items-center justify-center rounded-md text-[15px] transition-colors duration-150"
                    >
                        <i class="hgi-stroke hgi-{{ $tab['icon'] }}" wire:loading.remove wire:target="setTab('{{ $key }}')"></i>
                        <i class="hgi-stroke hgi-loading-03 animate-spin" wire:loading wire:target="setTab('{{ $key }}')"></i>
                    </span>

                    {{-- Label --}}
                    <span class="min-w-0 flex-1 truncate leading-snug">{{ $tab['label'] }}</span>

                    {{-- Badge --}}
                    @isset($tab['badge'])
                        @if (($tab['badge_tone'] ?? null) === 'alert')
                            <span class="ml-auto inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-semibold leading-none tabular-nums {{ $ui['badgeAlert'] }}">
                                {{ $tab['badge'] }}
                            </span>
                        @else
                            <span
                                :class="active === '{{ $key }}' ? '{{ $ui['badgeActive'] }}' : '{{ $ui['badgeIdle'] }}'"
                                class="ml-auto inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-medium leading-none tabular-nums transition-colors duration-150"
                            >
                                {{ $tab['badge'] }}
                            </span>
                        @endif
                    @endisset
                </button>
            @endforeach
        </div>
    </nav>
</div>
