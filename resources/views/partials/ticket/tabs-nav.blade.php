{{-- Attend : $tabs, $activeTab --}}

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
<nav class="hidden sm:sticky sm:top-4 sm:block sm:w-52 sm:shrink-0 lg:w-60" aria-label="Tabs">
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
