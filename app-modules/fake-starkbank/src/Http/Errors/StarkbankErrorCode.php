<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Http\Errors;

use Filament\Support\Colors\Color;
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
 * `invalidRequest` também cobre corpo e parâmetros que um endpoint recusa,
 * `invalidId` é a releitura de um recurso que este fake nunca emitiu e
 * `invalidDictKey` é a chave PIX que o registro DICT não conhece.
 *
 * Quatro deles são as recusas da perna de BR Code: `invalidBrcode` para um EMV
 * que não decodifica, e `invalidJson`/`invalidTaxId`/`invalidAmount` para um
 * pagamento que descreve algo diferente do que o código carrega.
 *
 * Os dois últimos não são recusa de conteúdo: são os switches globais do
 * switchboard de cenários, que derrubam a rota antes de qualquer lógica.
 */
enum StarkbankErrorCode: string implements HasColor, HasDescription, HasLabel
{
    case InvalidAccessId = 'invalidAccessId';
    case InvalidSignature = 'invalidSignature';
    case ExpiredAccessTime = 'expiredAccessTime';
    case InvalidRequest = 'invalidRequest';
    case InvalidId = 'invalidId';
    case InvalidDictKey = 'invalidDictKey';
    case InvalidBrcode = 'invalidBrcode';
    case InvalidJson = 'invalidJson';
    case InvalidTaxId = 'invalidTaxId';
    case InvalidAmount = 'invalidAmount';
    case InternalServerError = 'internalServerError';
    case TooManyRequests = 'tooManyRequests';

    public function defaultMessage(): string
    {
        return match ($this) {
            self::InvalidAccessId => 'Invalid access id',
            self::InvalidSignature => 'Invalid access signature',
            self::ExpiredAccessTime => 'Expired access time',
            self::InvalidRequest => 'Invalid request',
            self::InvalidId => 'Invalid id',
            self::InvalidDictKey => 'PIX key not found',
            self::InvalidBrcode => 'Invalid brcode',
            self::InvalidJson => 'Invalid json',
            self::InvalidTaxId => 'Invalid tax id',
            self::InvalidAmount => 'Invalid amount',
            self::InternalServerError => 'Internal server error',
            self::TooManyRequests => 'Too many requests',
        };
    }

    public function httpStatus(): int
    {
        return match ($this) {
            self::InvalidAccessId, self::InvalidSignature, self::ExpiredAccessTime => 401,
            self::InvalidRequest, self::InvalidBrcode, self::InvalidJson, self::InvalidTaxId, self::InvalidAmount => 400,
            self::InvalidId, self::InvalidDictKey => 404,
            self::TooManyRequests => 429,
            self::InternalServerError => 503,
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
            self::InvalidDictKey => 'Chave PIX não registrada',
            self::InvalidBrcode => 'BR Code ilegível',
            self::InvalidJson => 'Parâmetro desconhecido ou ausente',
            self::InvalidTaxId => 'Recebedor divergente do BR Code',
            self::InvalidAmount => 'Valor divergente do BR Code',
            self::InternalServerError => 'Provedor indisponível',
            self::TooManyRequests => 'Excesso de requisições',
        };
    }

    /**
     * Enum não-ordenado: os códigos são causas distintas, não uma escala de
     * severidade — cada case recebe uma cor própria, sem ramp. Os cinco
     * primeiros esgotaram as cores semânticas curtas; do sexto em diante o
     * valor vem da paleta do Filament, que o contrato `HasColor` aceita como
     * array.
     *
     * @return string|array<int, string>
     */
    public function getColor(): string|array
    {
        return match ($this) {
            self::InvalidAccessId => 'danger',
            self::InvalidSignature => 'warning',
            self::ExpiredAccessTime => 'info',
            self::InvalidRequest => 'gray',
            self::InvalidId => 'primary',
            self::InvalidDictKey => Color::Rose,
            self::InvalidBrcode => Color::Amber,
            self::InvalidJson => Color::Teal,
            self::InvalidTaxId => Color::Fuchsia,
            self::InvalidAmount => Color::Lime,
            self::InternalServerError => Color::Violet,
            self::TooManyRequests => Color::Cyan,
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
            self::InvalidDictKey => 'A chave PIX consultada não está registrada no DICT deste fake',
            self::InvalidBrcode => 'O BR Code não decodifica: TLV truncado, CRC16 que não fecha ou sem o arranjo br.gov.bcb.pix',
            self::InvalidJson => 'Parâmetro que este endpoint não aceita (externalId) ou obrigatório e ausente (description)',
            self::InvalidTaxId => 'O taxId do pagamento não é o titular da chave embutida no BR Code',
            self::InvalidAmount => 'O amount do pagamento não é o valor embutido no campo 54 do BR Code',
            self::InternalServerError => 'Modo outage armado no switchboard: toda rota /v2/* cai antes de qualquer lógica',
            self::TooManyRequests => 'Modo rate limit armado no switchboard: toda rota /v2/* recusa com Retry-After',
        };
    }
}
