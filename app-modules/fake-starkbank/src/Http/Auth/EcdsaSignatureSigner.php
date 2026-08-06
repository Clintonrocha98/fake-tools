<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Http\Auth;

use phpseclib3\Crypt\EC;
use phpseclib3\Crypt\EC\PrivateKey;
use Throwable;

/**
 * A perna inversa do {@see EcdsaSignatureVerifier}: assina uma mensagem com
 * ECDSA secp256k1 sobre SHA-256 e devolve o DER em base64 — a forma de wire que
 * `DigitalSignatureVerifier` do consumidor verifica no header
 * `Digital-Signature`.
 *
 * Puro e sem efeito colateral: o PEM entra pelo construtor, nunca é lido da
 * config aqui, então a assinatura é exercitável contra um par gerado em teste
 * sem tocar config nem rede — e {@see \He4rt\FakeStarkbank\Webhook\Actions\EmitCorrupted}
 * consegue assinar com uma chave alheia sem nenhum caminho especial.
 *
 * Falha FECHADA: PEM vazio, chave que não é EC ou qualquer erro devolvem
 * `null`; quem chama trata como emissão abortada e loga o porquê.
 */
final readonly class EcdsaSignatureSigner
{
    public function __construct(private string $privateKeyPem) {}

    public function sign(string $message): ?string
    {
        if (mb_trim($this->privateKeyPem) === '') {
            return null;
        }

        try {
            $key = EC::loadPrivateKey($this->privateKeyPem);

            if (!$key instanceof PrivateKey) {
                return null;
            }

            // withHash() clona a chave com SHA-256 (o esquema do StarkBank);
            // phpseclib tipa o retorno de forma larga, então re-estreite antes
            // de assinar.
            $signingKey = $key->withHash('sha256');

            if (!$signingKey instanceof PrivateKey) {
                return null;
            }

            $signature = $signingKey->sign($message);

            return is_string($signature) && $signature !== '' ? base64_encode($signature) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
