<?php

declare(strict_types=1);

namespace He4rt\Venue\Tests\Support;

/**
 * Assina requests de teste exatamente como o `BinanceConnector` do monolito
 * consumidor assina: HMAC-SHA256 de `http_build_query` sobre a query com
 * `timestamp` (+ `recvWindow`, quando > 0) já adicionados — SEM `signature`,
 * que entra por último. Reutilizável pelos tickets seguintes, que também
 * precisam de requests assinados batendo com o fake.
 */
trait SignsRequests
{
    public const string API_KEY = 'test-api-key';

    public const string API_SECRET = 'test-api-secret';

    public const int RECV_WINDOW = 5_000;

    /**
     * Configura o par de credenciais do fake para bater com {@see self::API_KEY}
     * e {@see self::API_SECRET} — chame no início de cada teste que assina requests.
     */
    protected function configureVenueCredentials(): void
    {
        config([
            'venue.api_key' => self::API_KEY,
            'venue.api_secret' => self::API_SECRET,
        ]);
    }

    /**
     * Monta a query assinada de um request: adiciona `timestamp`/`recvWindow` e
     * assina o resultado (sem `signature`) com {@see self::API_SECRET}.
     *
     * @param  array<string, scalar>  $params
     * @return array<string, scalar>
     */
    protected function signedQuery(
        array $params = [],
        ?int $timestamp = null,
        ?int $recvWindow = self::RECV_WINDOW,
        string $secret = self::API_SECRET,
    ): array {
        $query = $params;
        $query['timestamp'] = $timestamp ?? now()->getTimestampMs();

        if ($recvWindow !== null && $recvWindow > 0) {
            $query['recvWindow'] = $recvWindow;
        }

        $query['signature'] = hash_hmac('sha256', http_build_query($query), $secret);

        return $query;
    }

    /**
     * O path de teste completo (path + query assinada), pronto para `getJson()`.
     *
     * @param  array<string, scalar>  $params
     */
    protected function signedUri(string $path, array $params = [], ?int $timestamp = null, ?int $recvWindow = self::RECV_WINDOW): string
    {
        return $path.'?'.http_build_query($this->signedQuery($params, $timestamp, $recvWindow));
    }

    /**
     * @return array<string, string>
     */
    protected function apiKeyHeader(string $apiKey = self::API_KEY): array
    {
        return ['X-MBX-APIKEY' => $apiKey];
    }
}
