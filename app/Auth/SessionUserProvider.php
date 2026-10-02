<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

class SessionUserProvider implements UserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        $data = session('cosmia_user');

        if (! $data || (string) $data['id'] !== (string) $identifier) {
            return null;
        }

        $user = new User([
            'name'  => $data['name'],
            'email' => $data['email'],
        ]);
        $user->id = $data['id'];

        return $user;
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
        //
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        return null;
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return false;
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        //
    }
}
