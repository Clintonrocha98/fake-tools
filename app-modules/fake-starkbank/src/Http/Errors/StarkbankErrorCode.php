<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Http\Errors;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * Os códigos de erro que o fake sabe emitir. Diferente da Binance (dois
 * dialetos de envelope), o StarkBank tem um único formato — `{"errors":
 * [{"code", "message"}]}` — montado exclusivamente por
 * {@see ErrorResponseFactory}.
 *
 * Os quatro cases de hoje são o vocabulário da autenticação: um por caminho de
 * rejeição do middleware `fake-starkbank.signed`, na ordem em que ele checa.
 */
enum StarkbankErrorCode: string implements HasColor, HasDescription, HasLabel
{
    case InvalidAccessId = 'invalidAccessId';
    case InvalidSignature = 'invalidSignature';
    case ExpiredAccessTime = 'expiredAccessTime';
    case InvalidRequest = 'invalidRequest';

    public function defaultMessage(): string
    {
        return match ($this) {
            self::InvalidAccessId => 'Invalid access id',
            self::InvalidSignature => 'Invalid access signature',
            self::ExpiredAccessTime => 'Expired access time',
            self::InvalidRequest => 'Missing Access-Id, Access-Time or Access-Signature header',
        };
    }

    public function httpStatus(): int
    {
        return match ($this) {
            self::InvalidAccessId, self::InvalidSignature, self::ExpiredAccessTime => 401,
            self::InvalidRequest => 400,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::InvalidAccessId => 'Access-Id desconhecido',
            self::InvalidSignature => 'Assinatura inválida',
            self::ExpiredAccessTime => 'Access-Time fora da janela',
            self::InvalidRequest => 'Request malformado',
        };
    }

    /**
     * Enum não-ordenado: os quatro códigos são causas distintas, não uma escala
     * de severidade — cada case recebe uma cor semântica própria, sem ramp.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::InvalidAccessId => 'danger',
            self::InvalidSignature => 'warning',
            self::ExpiredAccessTime => 'info',
            self::InvalidRequest => 'gray',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::InvalidAccessId => 'Header Access-Id não confere com fake-starkbank.client.access_id',
            self::InvalidSignature => 'verify() recusou a Access-Signature — chave errada, mensagem errada ou base64 corrompido',
            self::ExpiredAccessTime => '|now − Access-Time| acima de fake-starkbank.recv_window_seconds',
            self::InvalidRequest => 'Falta ao menos um dos três headers Access-Id/Access-Time/Access-Signature',
        };
    }
}
