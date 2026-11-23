{{-- resources/views/components/project-card.blade.php --}}
@props([
    'name',
    'code'     => null,
    'active'   => true,
    'pending'  => 0,
    'progress' => 0,
    'closed'   => 0,
])

<section {{ $attributes->class('rounded-xl border border-zinc-300/70 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800') }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0 space-y-0.5">
            <div class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $name }}</div>

            @if ($code)
                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $code }}</div>
            @endif
        </div>

        @if ($active)
            <flux:badge color="green" size="sm">{{ __('Actif') }}</flux:badge>
        @endif
    </div>

    <div class="mt-4 grid grid-cols-3 gap-3">
        <div>
            <div class="text-lg font-medium text-zinc-900 dark:text-white">{{ number_format($pending, 0, ',', ' ') }}</div>
            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('En attente') }}</div>
        </div>

        <div>
            <div class="text-lg font-medium text-zinc-900 dark:text-white">{{ number_format($progress, 0, ',', ' ') }}</div>
            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('En cours') }}</div>
        </div>

        <div>
            <div class="text-lg font-medium text-zinc-900 dark:text-white">{{ number_format($closed, 0, ',', ' ') }}</div>
            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Clôturés') }}</div>
        </div>
    </div>
</section>
