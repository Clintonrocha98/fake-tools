<?php

declare(strict_types=1);

namespace He4rt\Venue\Http\Middleware;

use Closure;
use He4rt\Venue\Http\Auth\HmacQuerySigner;
use He4rt\Venue\Http\Errors\BinanceErrorCode;
use He4rt\Venue\Http\Errors\ErrorFamily;
use He4rt\Venue\Http\Errors\VenueErrorResponseFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verificação de assinatura Binance: `X-MBX-APIKEY`, janela de `timestamp`/`recvWindow`
 * e HMAC-SHA256 da query SEM o parâmetro `signature` — exatamente como o
 * `BinanceConnector` do monolito assina (query only, body JSON fora da assinatura).
 * O envelope de erro é escolhido pela família do path antes de qualquer outra
 * checagem, para que toda rejeição — inclusive a de API key — já saia no formato certo.
 */
final readonly class VerifiesSignedRequest
{
    public function __construct(private VenueErrorResponseFactory $errors) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $family = ErrorFamily::fromPath($request->path());

        $apiKey = $request->header('X-MBX-APIKEY');

        if ($apiKey === null || $apiKey === '') {
            return $this->errors->make($family, BinanceErrorCode::ApiKeyMissing);
        }

        if (!hash_equals(config()->string('venue.api_key'), $apiKey)) {
            return $this->errors->make($family, BinanceErrorCode::ApiKeyInvalid);
        }

        /** @var array<string, mixed> $query */
        $query = $request->query();
        $timestamp = $query['timestamp'] ?? null;

        if (!is_numeric($timestamp) || !$this->withinRecvWindow((int) $timestamp, $query)) {
            return $this->errors->make($family, BinanceErrorCode::TimestampOutOfWindow);
        }

        $signature = $query['signature'] ?? null;

        if (!is_string($signature) || $signature === '' || !hash_equals($this->expectedSignature($query), $signature)) {
            return $this->errors->make($family, BinanceErrorCode::InvalidSignature);
        }

        return $next($request);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function withinRecvWindow(int $timestamp, array $query): bool
    {
        $recvWindow = isset($query['recvWindow']) && is_numeric($query['recvWindow'])
            ? min((int) $query['recvWindow'], 60_000)
            : config()->integer('venue.recv_window');

        $serverTime = now()->getTimestampMs();

        return $timestamp >= $serverTime - $recvWindow && $timestamp <= $serverTime + 1_000;
    }

    /**
     * Assina a query recebida — sem `signature` — com o mesmo `http_build_query`
     * que o monolito usa para gerar a string original, preservando a ordem em
     * que os parâmetros chegaram na URL.
     *
     * @param  array<string, mixed>  $query
     */
    private function expectedSignature(array $query): string
    {
        unset($query['signature']);

        return new HmacQuerySigner(config()->string('venue.api_secret'))->sign(http_build_query($query));
    }
}
