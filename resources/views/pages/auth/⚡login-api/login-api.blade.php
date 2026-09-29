<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Se connecter à votre compte')" :description="__('Saisissez votre adresse e-mail et votre mot de passe ci-dessous pour vous connecter')" />

    @error('form')
    <flux:callout variant="danger" icon="exclamation-circle" :heading="$message" />
    @enderror

    <form wire:submit="login" class="flex flex-col gap-6">
        <!-- Adresse e-mail -->
        <flux:input
            wire:model="email"
            :label="__('Adresse e-mail')"
            type="email"
            required
            autofocus
            autocomplete="email"
            placeholder="email@exemple.com"
        />

        <!-- Mot de passe -->
        <div class="relative">
            <flux:input
                wire:model="password"
                :label="__('Mot de passe')"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('Mot de passe')"
                viewable
            />
        </div>

        <!-- Se souvenir de moi -->
        <flux:checkbox wire:model="remember" :label="__('Se souvenir de moi')" />

        <div class="flex items-center justify-end">
            <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                {{ __('Se connecter') }}
            </flux:button>
        </div>
    </form>
</div>
