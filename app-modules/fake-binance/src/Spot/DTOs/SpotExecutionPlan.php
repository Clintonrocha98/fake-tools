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
     * Status de wire do desfecho de vocabulário desconhecido quando o operador
     * não informou um. Sem este default o desfecho seria um no-op silencioso: o
     * switch fica verde, o cenário é consumido e a resposta sai FILLED limpa.
     */
    public const string DEFAULT_UNKNOWN_RAW_STATUS = 'SOME_FUTURE_STATE';

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
        return new self('1', OrderStatus::Filled, refusal: null, rawStatusOverride: null);
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
     * A fração incide sobre UMA grandeza só — a quantidade base executada, na
     * precisão do ativo base. Todo total quote é derivado desse resultado
     * ({@see \He4rt\FakeBinance\Spot\Actions\PlaceMarketOrder}); fracionar
     * qty e quote em paralelo os separaria em qualquer fração que não feche na
     * precisão da base, e a wire reportaria um total que o ledger não moveu.
     *
     * O atalho da fração `1` devolve o valor intocado de propósito: é ele que
     * garante que o plano neutro não reescale nada, mantendo o happy path bit a
     * bit igual ao de antes do mecanismo de cenário existir.
     *
     * @param  numeric-string  $amount
     * @return numeric-string
     */
    public function applyFraction(string $amount, int $scale): string
    {
        return $this->fillFraction === '1' ? $amount : bcmul($amount, $this->fillFraction, $scale);
    }
}
