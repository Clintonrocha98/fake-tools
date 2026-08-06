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
 * Quatro deles são o vocabulário da autenticação: um por caminho de rejeição
 * do middleware `fake-starkbank.signed`, na ordem em que ele checa.
 * `invalidRequest` também cobre corpo e parâmetros que um endpoint recusa, e
 * `invalidId` é a releitura de um recurso que este fake nunca emitiu.
 */
enum StarkbankErrorCode: string implements HasColor, HasDescription, HasLabel
{
    case InvalidAccessId = 'invalidAccessId';
    case InvalidSignature = 'invalidSignature';
    case ExpiredAccessTime = 'expiredAccessTime';
    case InvalidRequest = 'invalidRequest';
    case InvalidId = 'invalidId';

    public function defaultMessage(): string
    {
        return match ($this) {
            self::InvalidAccessId => 'Invalid access id',
            self::InvalidSignature => 'Invalid access signature',
            self::ExpiredAccessTime => 'Expired access time',
            self::InvalidRequest => 'Invalid request',
            self::InvalidId => 'Invalid id',
        };
    }

    public function httpStatus(): int
    {
        return match ($this) {
            self::InvalidAccessId, self::InvalidSignature, self::ExpiredAccessTime => 401,
            self::InvalidRequest => 400,
            self::InvalidId => 404,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::InvalidAccessId => 'Access-Id desconhecido',
            self::InvalidSignature => 'Assinatura inválida',
            self::ExpiredAccessTime => 'Access-Time fora da janela',
            self::InvalidRequest => 'Request malformado',
            self::InvalidId => 'Id desconhecido',
        };
    }

    /**
     * Enum não-ordenado: os códigos são causas distintas, não uma escala de
     * severidade — cada case recebe uma cor semântica própria, sem ramp.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::InvalidAccessId => 'danger',
            self::InvalidSignature => 'warning',
            self::ExpiredAccessTime => 'info',
            self::InvalidRequest => 'gray',
            self::InvalidId => 'primary',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::InvalidAccessId => 'Header Access-Id não confere com fake-starkbank.client.access_id',
            self::InvalidSignature => 'verify() recusou a Access-Signature — chave errada, mensagem errada ou base64 corrompido',
            self::ExpiredAccessTime => '|now − Access-Time| acima de fake-starkbank.recv_window_seconds',
            self::InvalidRequest => 'Headers de assinatura ausentes, ou corpo/parâmetros que o endpoint não aceita — quem rejeita passa a mensagem específica',
            self::InvalidId => 'Nenhum recurso com esse id foi emitido por este fake',
        };
    }
}
