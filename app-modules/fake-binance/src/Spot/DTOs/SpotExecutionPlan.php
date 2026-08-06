<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\DTOs;

use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;
use He4rt\FakeBinance\Spot\Enums\OrderStatus;

/**
 * O que {@see \He4rt\FakeBinance\Spot\Actions\PlaceMarketOrder} deve fazer com o
 * próximo pedido. O happy path não é um `if` ausente: é o plano neutro (fração
 * `1`, FILLED, sem recusa), então a Action tem UM caminho só e nunca conhece o
 * vocabulário de cenário.
 */
final readonly class SpotExecutionPlan
{
    /** Fração do desfecho parcial quando o operador não informou uma. */
    public const string DEFAULT_PARTIAL_FRACTION = '0.5';

    /**
     * @param  numeric-string  $fillFraction
     */
    public function __construct(
        public string $fillFraction,
        public OrderStatus $finalStatus,
        public ?BinanceErrorCode $refusal,
        public ?string $rawStatusOverride,
    ) {}

    public static function neutral(): self
    {
        return new self('1', OrderStatus::Filled, null, null);
    }

    public function refuses(): bool
    {
        return $this->refusal instanceof BinanceErrorCode;
    }

    public function fillsNothing(): bool
    {
        return bccomp($this->fillFraction, '0', 18) <= 0;
    }

    /**
     * A fração é aplicada na MESMA escala em que o valor original foi
     * calculado — `executedQty` na precisão da base, `cummulativeQuoteQty` na
     * escala do ledger. Multiplicar tudo numa escala só faria a wire reportar
     * um número que o ledger não moveu.
     *
     * @param  numeric-string  $amount
     * @return numeric-string
     */
    public function applyFraction(string $amount, int $scale): string
    {
        return $this->fillFraction === '1' ? $amount : bcmul($amount, $this->fillFraction, $scale);
    }
}
