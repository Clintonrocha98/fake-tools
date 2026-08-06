<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Http\Middleware;

use Closure;
use He4rt\FakeStarkbank\Http\Auth\ClientPublicKey;
use He4rt\FakeStarkbank\Http\Auth\EcdsaSignatureVerifier;
use He4rt\FakeStarkbank\Http\Auth\SignedRequestMessage;
use He4rt\FakeStarkbank\Http\Errors\ErrorResponseFactory;
use He4rt\FakeStarkbank\Http\Errors\StarkbankErrorCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verificação da Request Signature do StarkBank: `Access-Id`, `Access-Time` e
 * `Access-Signature` sobre a mensagem `accessId:accessTime:body`.
 *
 * A ordem das quatro checagens é o contrato: headers presentes → Access-Id
 * conhecido → Access-Time dentro da janela → assinatura verificada. Uma
 * mensagem incompleta nunca chega ao `verify()`, então `invalidSignature`
 * significa sempre "assinei, mas não bate" — nunca "faltou header".
 */
final readonly class VerifiesSignedRequest
{
    public function __construct(
        private ErrorResponseFactory $errors,
        private ClientPublicKey $publicKey,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $accessId = (string) $request->header('Access-Id');
        $accessTime = (string) $request->header('Access-Time');
        $signature = (string) $request->header('Access-Signature');

        if ($accessId === '' || $accessTime === '' || $signature === '') {
            Log::warning('fake-starkbank.auth: request recusado — falta ao menos um dos três headers de assinatura, e mensagem incompleta nunca é verificada', [
                'path' => $request->path(),
                'has_access_id' => $accessId !== '',
                'has_access_time' => $accessTime !== '',
                'has_access_signature' => $signature !== '',
            ]);

            return $this->errors->make(
                StarkbankErrorCode::InvalidRequest,
                'Missing Access-Id, Access-Time or Access-Signature header',
            );
        }

        if (!hash_equals((string) config('fake-starkbank.client.access_id', ''), $accessId)) {
            Log::warning('fake-starkbank.auth: request recusado — Access-Id não é o cliente configurado, então nem faz sentido verificar a assinatura', [
                'path' => $request->path(),
                'access_id' => $accessId,
            ]);

            return $this->errors->make(StarkbankErrorCode::InvalidAccessId);
        }

        if (!$this->withinRecvWindow($accessTime)) {
            Log::warning('fake-starkbank.auth: request recusado — Access-Time fora da janela configurada, request velho ou relógio dessincronizado', [
                'path' => $request->path(),
                'access_time' => $accessTime,
                'recv_window_seconds' => $this->recvWindowSeconds(),
            ]);

            return $this->errors->make(StarkbankErrorCode::ExpiredAccessTime);
        }

        $message = SignedRequestMessage::compose($accessId, $accessTime, $request->getContent());

        if (!new EcdsaSignatureVerifier($this->publicKey->pem())->verify($message, $signature)) {
            Log::warning('fake-starkbank.auth: request recusado — assinatura ECDSA não confere com a chave pública do cliente (chave rotacionada, mensagem divergente ou base64 corrompido)', [
                'path' => $request->path(),
                'access_id' => $accessId,
                'body_bytes' => mb_strlen($request->getContent(), '8bit'),
            ]);

            return $this->errors->make(StarkbankErrorCode::InvalidSignature);
        }

        Log::debug('fake-starkbank.auth: request autenticado — assinatura confere sobre accessId:accessTime:body', [
            'path' => $request->path(),
            'access_id' => $accessId,
        ]);

        return $next($request);
    }

    /**
     * Um Access-Time presente mas não numérico é tratado como fora da janela,
     * nunca como header ausente: o header veio, só não descreve um instante —
     * e nenhum instante fora de `|now − Access-Time| ≤ recv_window_seconds`
     * autentica.
     */
    private function withinRecvWindow(string $accessTime): bool
    {
        if (!is_numeric($accessTime)) {
            return false;
        }

        return abs(now()->getTimestamp() - (int) $accessTime) <= $this->recvWindowSeconds();
    }

    private function recvWindowSeconds(): int
    {
        // `config()->integer()` exigiria um int estrito — a numeric-string que
        // `env()` produz de um `.env` real explodiria ali.
        return (int) config('fake-starkbank.recv_window_seconds', 300);
    }
}
