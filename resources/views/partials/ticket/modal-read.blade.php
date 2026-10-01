{{-- Modale : marquer comme lu --}}
<flux:modal wire:model.self="showReadModal" class="md:w-96">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __("Confirmation de l'action") }}</flux:heading>
            <flux:text class="mt-2">
                {{ __('Êtes-vous sûr de vouloir marquer les messages comme lus ? Cette opération est irréversible.') }}
            </flux:text>
        </div>

        <div class="flex gap-2">
            <flux:spacer />
            <flux:modal.close><flux:button variant="danger">{{ __('Annuler') }}</flux:button></flux:modal.close>
            <flux:button variant="primary" wire:click="confirmerActionLu">{{ __('Confirmer') }}</flux:button>
        </div>
    </div>
</flux:modal>
