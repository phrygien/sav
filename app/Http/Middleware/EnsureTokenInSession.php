<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenInSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = session('cosmia_token');

        if (! $token) {
            return $this->logoutAndRedirect($request, 'Veuillez vous connecter');
        }

        if ($this->isTokenExpired($token)) {
            return $this->logoutAndRedirect($request, 'Votre session a expiré');
        }

        return $next($request);
    }

    /**
     * Vérifie l'expiration d'un JWT via le claim "exp".
     * Si le token est illisible, on le considère comme expiré.
     */
    protected function isTokenExpired(string $token): bool
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return true;
        }

        $payload = json_decode(
            base64_decode(strtr($parts[1], '-_', '+/')),
            true
        );

        if (! is_array($payload) || ! isset($payload['exp'])) {
            return true;
        }

        return $payload['exp'] <= time();
    }

    protected function logoutAndRedirect(Request $request, string $message): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('auth.login')->with('error', $message);
    }
}
