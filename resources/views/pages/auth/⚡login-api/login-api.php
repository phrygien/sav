<?php

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts::auth.split')] class extends Component
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();

        $key = Str::transliterate(Str::lower($this->email).'|'.request()->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'form' => __('Too many login attempts. Try again in :s seconds.', [
                    's' => RateLimiter::availableIn($key),
                ]),
            ]);
        }

        try {
            $response = Http::baseUrl(config('services.cosmia.url'))
                ->withHeaders(['x-secret-key' => config('services.cosmia.secret')])
                ->acceptJson()
                ->asJson()
                ->timeout(10)
                ->post('/auth/login', [
                    'email'    => $this->email,
                    'password' => $this->password,
                ]);
        } catch (ConnectionException) {
            throw ValidationException::withMessages([
                'form' => __('Authentication service unavailable.'),
            ]);
        }

        if ($response->failed()) {
            RateLimiter::hit($key);

            $apiError = $response->json('error') ?? $response->json('message');

            $message = match ($apiError) {
                'User not found'   => __('Utilisateur introuvable'),
                'Invalid password' => __('Mot de passe incorrect'),
                default            => $apiError ?? __('auth.failed'),
            };

            throw ValidationException::withMessages([
                'form' => $message,
            ]);
        }

        RateLimiter::clear($key);

        // ⚠️ Adapter aux clés réellement renvoyées par l'API
        $data = $response->json();

        $user = User::updateOrCreate(
            ['email' => $this->email],
            [
                'name'     => data_get($data, 'user.name', $this->email),
                'password' => Hash::make(Str::random(40)), // le vrai mot de passe reste côté API
            ]
        );

        Auth::login($user, $this->remember);
        session()->regenerate();
        session(['cosmia_token' => data_get($data, 'token')]);

        $this->redirectIntended(route('dashboard'), navigate: true);
    }
};
