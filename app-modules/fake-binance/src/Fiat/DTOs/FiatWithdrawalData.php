<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Fiat\DTOs;

/**
 * O body JSON de POST /sapi/v2/fiat/withdraw como o `RequestFiatWithdrawalRequest`
 * do monolito consumidor o manda: `currency`, `apiPaymentMethod`, `amount`,
 * `accountInfo` (aqui achatado nos campos que a doc documenta) e o
 * `clientOrderId` de idempotência.
 */
final readonly class FiatWithdrawalData
{
    /**
     * `$amount` chega cru da wire (body JSON) — validado como decimal por
     * {@see \He4rt\FakeBinance\Fiat\Http\Requests\RequestFiatWithdrawalRequest},
     * mas nunca `numeric-string` estaticamente neste limite de entrada.
     */
    public function __construct(
        public string $currency,
        public string $paymentMethod,
        public string $amount,
        public string $accountNumber,
        public string $clientOrderId,
        public ?string $agency = null,
        public ?string $bankCodeForPix = null,
        public ?string $accountType = null,
    ) {}
}
