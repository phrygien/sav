<?php

namespace App\Services;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CosmiaApi
{
    /**
     * @param  bool  $withUserToken  false = appel avec la seule clé x-secret-key (sans Bearer)
     */
    public function get(string $path, array $query = [], bool $withUserToken = true): array
    {
        return $this->send(
            fn (PendingRequest $request) => $request->get($path, $query),
            $withUserToken
        );
    }

    public function post(string $path, array $body = []): array
    {
        return $this->send(fn (PendingRequest $request) => $request->post($path, $body));
    }

    public function put(string $path, array $body = []): array
    {
        return $this->send(fn (PendingRequest $request) => $request->put($path, $body));
    }

    public function delete(string $path, array $query = []): array
    {
        return $this->send(fn (PendingRequest $request) => $request->delete($path, $query));
    }

    /**
     * Lance plusieurs GET en parallèle.
     *
     * @param  array<string|int, string>  $paths  [clé => chemin]
     * @return array<string|int, array|null>  [clé => réponse JSON, ou null si cet appel a échoué]
     */
    public function getMany(array $paths): array
    {
        if (empty($paths)) {
            return [];
        }

        $token = session('cosmia_token');

        $responses = Http::pool(function (Pool $pool) use ($paths, $token) {
            $calls = [];

            foreach ($paths as $key => $path) {
                $request = $pool->as((string) $key)
                    ->baseUrl(config('services.cosmia.url'))
                    ->withHeaders(['x-secret-key' => config('services.cosmia.secret')])
                    ->acceptJson()
                    ->asJson()
                    ->timeout(15);

                if ($token) {
                    $request = $request->withToken($token);
                }

                $calls[] = $request->get($path);
            }

            return $calls;
        });

        $results = [];

        foreach ($paths as $key => $path) {
            $response = $responses[(string) $key] ?? null;

            $results[$key] = ($response instanceof Response && $response->successful())
                ? ($response->json() ?? [])
                : null;
        }

        return $results;
    }

    /**
     * Lance plusieurs GET (avec query) en parallèle, en conservant le message d'erreur de chaque appel.
     *
     * @param  array<string|int, array{0: string, 1?: array}>  $requests  [clé => [chemin, query]]
     * @return array<string|int, array|RuntimeException>  [clé => réponse JSON, ou RuntimeException (non levée)]
     */
    public function pool(array $requests): array
    {
        if (empty($requests)) {
            return [];
        }

        $token = session('cosmia_token');

        $responses = Http::pool(function (Pool $pool) use ($requests, $token) {
            $calls = [];

            foreach ($requests as $key => $request) {
                $pending = $pool->as((string) $key)
                    ->baseUrl(config('services.cosmia.url'))
                    ->withHeaders(['x-secret-key' => config('services.cosmia.secret')])
                    ->acceptJson()
                    ->asJson()
                    ->timeout(15);

                if ($token) {
                    $pending = $pending->withToken($token);
                }

                $calls[] = $pending->get($request[0], $request[1] ?? []);
            }

            return $calls;
        });

        $results = [];

        foreach ($requests as $key => $_) {
            $response = $responses[(string) $key] ?? null;

            if ($response instanceof Response) {
                $results[$key] = $response->failed()
                    ? new RuntimeException(
                        $response->json('error')
                        ?? $response->json('message')
                        ?? __("Erreur de l'API (:status).", ['status' => $response->status()])
                    )
                    : ($response->json() ?? []);

                continue;
            }

            // ConnectionException (ou aucune réponse)
            $results[$key] = new RuntimeException(__('Service indisponible, réessaie dans un instant.'));
        }

        return $results;
    }

    /**
     * @throws RuntimeException Le message est directement affichable à l'utilisateur.
     */
    private function send(Closure $call, bool $withUserToken = true): array
    {
        $request = Http::baseUrl(config('services.cosmia.url'))
            ->withHeaders(['x-secret-key' => config('services.cosmia.secret')])
            ->acceptJson()
            ->asJson()
            ->timeout(15);

        if ($withUserToken && ($token = session('cosmia_token'))) {
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
