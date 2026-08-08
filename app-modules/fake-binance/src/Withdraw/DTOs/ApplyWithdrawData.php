<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Withdraw\DTOs;

/**
 * O que `ApplyWithdrawRequest` do monolito manda na query do apply — `amount` é
 * sempre string decimal, nunca float. `withdrawOrderId` é o correlationId do Payout
 * (idempotência); ausente, o apply nunca dedupe. `network` é OPCIONAL na doc da
 * venue: omitido, vale a rede default da coin
 * ({@see \He4rt\FakeBinance\Withdraw\Actions\ApplyWithdraw::resolveNetwork()}).
 */
final readonly class ApplyWithdrawData
{
    /**
     * @param  numeric-string  $amount
     */
    public function __construct(
        public string $coin,
        public string $address,
        public string $amount,
        public ?string $network = null,
        public ?string $withdrawOrderId = null,
        public ?string $addressTag = null,
    ) {}
}
