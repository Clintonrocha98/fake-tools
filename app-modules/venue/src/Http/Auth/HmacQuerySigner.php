<?php

declare(strict_types=1);

namespace He4rt\Venue\Http\Auth;

/**
 * Recalcula a assinatura Binance do lado do fake: HMAC-SHA256 hex minúsculo da
 * query string, com o mesmo segredo configurado. Espelha
 * {@see \Brd\IntegrationBinance\Http\Auth\HmacSigner}, o signer usado pelo
 * monolito consumidor — o fake precisa reproduzir exatamente o mesmo cálculo
 * para aceitar as assinaturas que ele produz.
 */
final readonly class HmacQuerySigner
{
    public function __construct(private string $secret) {}

    public function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secret);
    }
}
