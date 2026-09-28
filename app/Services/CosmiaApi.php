<?php

namespace App\Services;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CosmiaApi
{
    public function get(string $path, array $query = []): array
    {
        return $this->send(fn (PendingRequest $request) => $request->get($path, $query));
    }

    public function post(string $path, array $body = []): array
    {
        return $this->send(fn (PendingRequest $request) => $request->post($path, $body));
    }

    public function put(string $path, array $body = []): array
    {
        return $this->send(fn (PendingRequest $request) => $request->put($path, $body));
    }

    /**
     * @throws RuntimeException Le message est directement affichable à l'utilisateur.
     */
    private function send(Closure $call): array
    {
        $request = Http::baseUrl(config('services.cosmia.url'))
            ->withHeaders(['x-secret-key' => config('services.cosmia.secret')])
            ->acceptJson()
            ->asJson()
            ->timeout(15);

        if ($token = session('cosmia_token')) {
            $request = $request->withToken($token);
        }

        try {
            $response = $call($request);
        } catch (ConnectionException) {
            throw new RuntimeException(__('Service indisponible, réessaie dans un instant.'));
        }

        if ($response->failed()) {
            throw new RuntimeException(
                $response->json('error')
                ?? $response->json('message')
                ?? __("Erreur de l'API (:status).", ['status' => $response->status()])
            );
        }

        return $response->json() ?? [];
    }

    public static function monthOptions(): array
    {
        return [
            'all' => 'Tous les mois',
            '01'  => 'Janvier',
            '02'  => 'Février',
            '03'  => 'Mars',
            '04'  => 'Avril',
            '05'  => 'Mai',
            '06'  => 'Juin',
            '07'  => 'Juillet',
            '08'  => 'Août',
            '09'  => 'Septembre',
            '10'  => 'Octobre',
            '11'  => 'Novembre',
            '12'  => 'Décembre',
        ];
    }

    public static function yearOptions(): array
    {
        $options = ['all' => 'Toutes les années'];

        foreach (range((int) date('Y'), (int) date('Y') - 4) as $year) {
            $options[(string) $year] = (string) $year;
        }

        return $options;
    }
}
