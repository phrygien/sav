<?php

namespace App\Livewire\Concerns;

use Flux\Flux;

trait NotifiesWithToast
{
    // variantes Flux : success | warning | danger (null / 'info' = neutre)
    protected function notify(string $message, ?string $variant = null): void
    {
        if ($variant === null || $variant === 'info') {
            Flux::toast(text: $message);

            return;
        }

        Flux::toast(text: $message, variant: $variant);
    }
}
