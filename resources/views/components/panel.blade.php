{{-- resources/views/components/panel.blade.php --}}
@props(['title' => null, 'description' => null])

<section {{ $attributes->class('rounded-2xl border border-zinc-200/70 bg-zinc-50 px-[3px] pb-[3px] dark:border-zinc-700/50 dark:bg-zinc-900/60') }}>
    {{-- En-tête : titre + description grise (+ actions à droite) --}}
    @if ($title || $description || isset($actions))
        <div class="flex items-start justify-between gap-4 px-4 pb-3 pt-3.5">
            <div class="min-w-0 space-y-1">
                @if ($title)
                    <div class="text-sm font-medium text-zinc-900 dark:text-white">{{ $title }}</div>
                @endif

                @if ($description)
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $description }}</div>
                @endif
            </div>

            @isset($actions)
                <div class="shrink-0">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    {{-- Carte blanche --}}
    <div class="rounded-xl border border-zinc-300/70 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
        {{ $slot }}
    </div>

    {{-- Pied optionnel (barre grise sous la carte) --}}
    @isset($footer)
        <div class="px-4 py-2.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $footer }}</div>
    @endisset
</section>
