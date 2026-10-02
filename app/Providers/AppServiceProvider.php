<?php

namespace App\Providers;

use App\Auth\SessionUserProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuth();

        $isSuperAdmin = fn () => session('cosmia_role') === 'super_admin';

        Gate::define('manage-access', $isSuperAdmin);       // affiche le menu « Accès & Sécurité »
        Gate::define('create-user', $isSuperAdmin);         // ajouter un utilisateur
        Gate::define('assign-user-project', $isSuperAdmin); // associer un utilisateur à un projet
    }

    /**
     * Register the custom session-based user provider (no local users table).
     */
    protected function configureAuth(): void
    {
        Auth::provider('session-user', fn () => new SessionUserProvider());
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
