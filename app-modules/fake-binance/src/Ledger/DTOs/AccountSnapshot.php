<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Ledger\DTOs;

use JsonSerializable;

/**
 * O shape de GET /api/v3/account que o monolito consome: só `balances[]` importa
 * ({@see \He4rt\FakeBinance\Ledger\Actions\GetAccountSnapshot}), os demais campos são
 * constantes da doc oficial da Binance, mantidas para fidelidade de shape.
 */
final readonly class AccountSnapshot implements JsonSerializable
{
    /**
     * @param  list<AccountBalance>  $balances
     */
    public function __construct(
        public array $balances,
        public int $updateTime,
        public int $makerCommission = 0,
        public int $takerCommission = 0,
        public bool $canTrade = true,
        public bool $canWithdraw = true,
        public bool $canDeposit = true,
        public string $accountType = 'SPOT',
    ) {}

    /**
     * @return array{
     *     makerCommission: int,
     *     takerCommission: int,
     *     canTrade: bool,
     *     canWithdraw: bool,
     *     canDeposit: bool,
     *     updateTime: int,
     *     accountType: string,
     *     balances: list<AccountBalance>,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'makerCommission' => $this->makerCommission,
            'takerCommission' => $this->takerCommission,
            'canTrade' => $this->canTrade,
            'canWithdraw' => $this->canWithdraw,
            'canDeposit' => $this->canDeposit,
            'updateTime' => $this->updateTime,
            'accountType' => $this->accountType,
            'balances' => $this->balances,
        ];
    }
}
