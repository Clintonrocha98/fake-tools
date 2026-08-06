<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Ledger\DTOs;

use JsonSerializable;

/**
 * O objeto `commissionRates` de GET /api/v3/account — taxas como decimal-string
 * de 8 casas, exatamente como a doc as serializa (`"0.00100000"`).
 */
final readonly class CommissionRates implements JsonSerializable
{
    public function __construct(
        public string $maker,
        public string $taker,
        public string $buyer = '0.00000000',
        public string $seller = '0.00000000',
    ) {}

    /**
     * @return array{maker: string, taker: string, buyer: string, seller: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'maker' => $this->maker,
            'taker' => $this->taker,
            'buyer' => $this->buyer,
            'seller' => $this->seller,
        ];
    }
}
