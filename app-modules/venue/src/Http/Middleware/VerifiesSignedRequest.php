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
        $signature = $query['signature'] ?? null;

        // `timestamp` e `signature` são mandatórios: ausentes ou malformados é -1102
        // (Binance real), nunca -1021/-1022 — esses dois códigos são exclusivos do
        // BinanceErrorBoundary do consumidor para "indisponibilidade retryable",
        // enquanto -1102 é fatal.
        if (!is_numeric($timestamp) || !is_string($signature) || $signature === '') {
            return $this->errors->make($family, BinanceErrorCode::MandatoryParameterMissing);
        }

        // A assinatura é verificada antes da janela de recvWindow — mesma ordem da
        // Binance real: um request com timestamp velho E assinatura errada responde
        // -1022, nunca -1021.
        if (!hash_equals($this->expectedSignature($query), $signature)) {
            return $this->errors->make($family, BinanceErrorCode::InvalidSignature);
        }

        $recvWindow = $this->resolveRecvWindow($query);

        if ($recvWindow === null || !$this->withinRecvWindow((int) $timestamp, $recvWindow)) {
            return $this->errors->make($family, BinanceErrorCode::TimestampOutOfWindow);
        }

        return $next($request);
    }

    /**
     * Resolve o `recvWindow` efetivo do request: default configurado quando ausente,
     * `null` quando o valor informado é inválido (não numérico, não positivo, ou acima
     * do teto de 60000ms) — a Binance real recusa esses casos com -1021 em vez de
     * silenciosamente aceitar um `recvWindow` maior que o permitido.
     *
     * @param  array<string, mixed>  $query
     */
    private function resolveRecvWindow(array $query): ?int
    {
        if (!isset($query['recvWindow'])) {
            // `config()->integer()` exige um `int` estrito — a mesma numeric-string
            // que `env()` produz do `.env` real o faria explodir aqui.
            return (int) config('venue.recv_window', 5_000);
        }

        $recvWindow = $query['recvWindow'];

        if (!is_numeric($recvWindow)) {
            return null;
        }

        $recvWindow = (int) $recvWindow;

        return $recvWindow > 0 && $recvWindow <= 60_000 ? $recvWindow : null;
    }

    private function withinRecvWindow(int $timestamp, int $recvWindow): bool
    {
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
