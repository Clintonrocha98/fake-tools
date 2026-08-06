<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Ledger\DTOs;

use JsonSerializable;

/**
 * O shape COMPLETO de GET /api/v3/account, não só o que o monolito consome
 * (`balances[]`, via {@see \He4rt\FakeBinance\Ledger\Actions\GetAccountSnapshot}).
 * As comissões refletem o `commission_rate` que a execução spot realmente
 * cobra (em basis points nos campos legados, decimal-string em
 * `commissionRates`); onde o fake não tem o conceito (`uid`, `permissions`,
 * `brokered`…), o valor constante da doc oficial.
 */
final readonly class AccountSnapshot implements JsonSerializable
{
    /**
     * @param  list<AccountBalance>  $balances
     * @param  list<string>  $permissions
     */
    public function __construct(
        public array $balances,
        public int $updateTime,
        public CommissionRates $commissionRates = new CommissionRates(maker: '0.00000000', taker: '0.00000000'),
        public int $makerCommission = 0,
        public int $takerCommission = 0,
        public int $buyerCommission = 0,
        public int $sellerCommission = 0,
        public bool $canTrade = true,
        public bool $canWithdraw = true,
        public bool $canDeposit = true,
        public bool $brokered = false,
        public bool $requireSelfTradePrevention = false,
        public bool $preventSor = false,
        public string $accountType = 'SPOT',
        public array $permissions = ['SPOT'],
        public int $uid = 354_937_868,
    ) {}

    /**
     * @return array{
     *     makerCommission: int,
     *     takerCommission: int,
     *     buyerCommission: int,
     *     sellerCommission: int,
     *     commissionRates: CommissionRates,
     *     canTrade: bool,
     *     canWithdraw: bool,
     *     canDeposit: bool,
     *     brokered: bool,
     *     requireSelfTradePrevention: bool,
     *     preventSor: bool,
     *     updateTime: int,
     *     accountType: string,
     *     balances: list<AccountBalance>,
     *     permissions: list<string>,
     *     uid: int,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'makerCommission' => $this->makerCommission,
            'takerCommission' => $this->takerCommission,
            'buyerCommission' => $this->buyerCommission,
            'sellerCommission' => $this->sellerCommission,
            'commissionRates' => $this->commissionRates,
            'canTrade' => $this->canTrade,
            'canWithdraw' => $this->canWithdraw,
            'canDeposit' => $this->canDeposit,
            'brokered' => $this->brokered,
            'requireSelfTradePrevention' => $this->requireSelfTradePrevention,
            'preventSor' => $this->preventSor,
            'updateTime' => $this->updateTime,
            'accountType' => $this->accountType,
            'balances' => $this->balances,
            'permissions' => $this->permissions,
            'uid' => $this->uid,
        ];
    }
}
