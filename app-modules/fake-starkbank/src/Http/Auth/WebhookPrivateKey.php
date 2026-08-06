<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Http\Auth;

use Illuminate\Support\Facades\Log;

/**
 * Resolve o PEM da chave privada com que o fake assina o header
 * `Digital-Signature` dos webhooks: o inline (`webhook.private_key`) vence,
 * senão o arquivo (`webhook.private_key_path`, absoluto ou relativo ao base
 * path).
 *
 * Par SEPARADO do de cliente (ADR-0001): aqui o fake é quem detém a chave
 * privada e o consumidor verifica com a pública; no sentido oposto os papéis se
 * invertem. Compartilhar um par só faria o fake conseguir forjar requests que
 * ele mesmo deveria estar verificando.
 *
 * Falha FECHADA: sem chave legível devolve string vazia e a emissão é abortada
 * — um webhook assinado com chave improvisada só produziria 401 do outro lado,
 * com o dev caçando um erro de assinatura que é de configuração.
 */
final readonly class WebhookPrivateKey
{
    public function pem(): string
    {
        $inline = mb_trim((string) config('fake-starkbank.webhook.private_key', ''));

        if ($inline !== '') {
            return $inline;
        }

        $path = mb_trim((string) config('fake-starkbank.webhook.private_key_path', ''));

        if ($path === '') {
            Log::warning('fake-starkbank.webhook: nenhuma chave privada de webhook configurada — nenhuma emissão será assinada até FAKE_STARKBANK_WEBHOOK_PRIVATE_KEY(_PATH) apontar para o par de dev');

            return '';
        }

        $resolved = str_starts_with($path, '/') ? $path : base_path($path);

        if (!is_readable($resolved)) {
            Log::warning('fake-starkbank.webhook: PEM da chave privada de webhook ilegível — nenhuma emissão será assinada', [
                'path' => $resolved,
            ]);

            return '';
        }

        $pem = file_get_contents($resolved);

        if ($pem === false) {
            Log::warning('fake-starkbank.webhook: falha ao ler o PEM da chave privada de webhook — nenhuma emissão será assinada', [
                'path' => $resolved,
            ]);

            return '';
        }

        return mb_trim($pem);
    }
}
