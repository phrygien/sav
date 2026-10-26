<?php

namespace App\Services;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CosmiaApi
{
    public const DEFAULT_TIMEOUT = 15;

    /**
     * @param  bool  $withUserToken  false = appel avec la seule clé x-secret-key (sans Bearer)
     * @param  int   $timeout        durée max de l'appel, en secondes
     */
    public function get(string $path, array $query = [], bool $withUserToken = true, int $timeout = self::DEFAULT_TIMEOUT): array
    {
        return $this->send(
            fn (PendingRequest $request) => $request->get($path, $query),
            $withUserToken,
            $timeout
        );
    }

    public function post(string $path, array $body = [], int $timeout = self::DEFAULT_TIMEOUT): array
    {
        return $this->send(
            fn (PendingRequest $request) => $request->post($path, $body),
            timeout: $timeout
        );
    }

    public function put(string $path, array $body = [], int $timeout = self::DEFAULT_TIMEOUT): array
    {
        return $this->send(
            fn (PendingRequest $request) => $request->put($path, $body),
            timeout: $timeout
        );
    }

    public function delete(string $path, array $query = [], int $timeout = self::DEFAULT_TIMEOUT): array
    {
        return $this->send(
            fn (PendingRequest $request) => $request->delete($path, $query),
            timeout: $timeout
        );
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
                    ->timeout(self::DEFAULT_TIMEOUT);

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
                    ->timeout(self::DEFAULT_TIMEOUT);

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
     *                          L'exception d'origine (cURL) reste accessible via getPrevious().
     */
    private function send(Closure $call, bool $withUserToken = true, int $timeout = self::DEFAULT_TIMEOUT): array
    {
        $request = Http::baseUrl(config('services.cosmia.url'))
            ->withHeaders(['x-secret-key' => config('services.cosmia.secret')])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout($timeout);

        if ($withUserToken && ($token = session('cosmia_token'))) {
            $request = $request->withToken($token);
        }

        try {
            $response = $call($request);
        } catch (ConnectionException $e) {
            Log::error('Cosmia connexion KO', [
                'url'     => config('services.cosmia.url'),
                'message' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                __('Service indisponible, réessaie dans un instant.'),
                previous: $e
            );
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
