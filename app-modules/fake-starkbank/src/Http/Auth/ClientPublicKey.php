<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Http\Auth;

use Illuminate\Support\Facades\Log;

/**
 * Resolve o PEM da chave pública do cliente a partir da config: o inline
 * (`client.public_key`) vence, senão o arquivo (`client.public_key_path`,
 * absoluto ou relativo ao base path). Trocar essa chave é o cenário de rotação
 * de credencial — todo request assinado com a chave antiga passa a sair em
 * `invalidSignature`.
 *
 * Falha FECHADA: sem chave legível devolve string vazia, e
 * {@see EcdsaSignatureVerifier} recusa toda assinatura. Um fake mal configurado
 * nunca autentica por engano — mas loga o porquê, para o dev não caçar um
 * `invalidSignature` que é de configuração, não de assinatura.
 */
final readonly class ClientPublicKey
{
    public function pem(): string
    {
        $inline = mb_trim((string) config('fake-starkbank.client.public_key', ''));

        if ($inline !== '') {
            return $inline;
        }

        $path = mb_trim((string) config('fake-starkbank.client.public_key_path', ''));

        if ($path === '') {
            Log::warning('fake-starkbank.auth: nenhuma chave pública de cliente configurada — toda assinatura será recusada até FAKE_STARKBANK_CLIENT_PUBLIC_KEY(_PATH) apontar para o par do consumidor');

            return '';
        }

        $resolved = str_starts_with($path, '/') ? $path : base_path($path);

        if (!is_readable($resolved)) {
            Log::warning('fake-starkbank.auth: PEM da chave pública do cliente ilegível — toda assinatura será recusada', [
                'path' => $resolved,
            ]);

            return '';
        }

        $pem = file_get_contents($resolved);

        if ($pem === false) {
            Log::warning('fake-starkbank.auth: falha ao ler o PEM da chave pública do cliente — toda assinatura será recusada', [
                'path' => $resolved,
            ]);

            return '';
        }

        return mb_trim($pem);
    }
}
