<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Withdraw\Actions;

use He4rt\FakeBinance\Ledger\Models\LedgerAccount;
use He4rt\FakeBinance\Ledger\Support\LedgerAmount;
use He4rt\FakeBinance\Withdraw\Exceptions\MisconfiguredWithdrawFeeException;

/**
 * GET /sapi/v1/capital/config/getall: um item por asset do ledger, cada um com
 * as redes e taxas que o withdraw/apply realmente cobra
 * (`fake-binance-withdraw.fees`) — o probe vê exatamente o custo que o apply
 * vai aplicar, nunca dois números para a mesma rede. O fake não modela o
 * mapeamento coin↔rede da venue real, então toda rede configurada aparece em
 * todo asset; `withdrawMin` espelha a própria fee — o menor saque que ainda a
 * cobre. O shape por linha é o que o `CoinsInfoResponse` do monolito
 * consumidor lê (`coin` + `networkList[]`).
 */
final readonly class GetCoinsInfo
{
    /**
     * @return list<array<string, mixed>>
     */
    public function handle(): array
    {
        $networks = $this->networks();

        $coins = LedgerAccount::query()
            ->orderBy('asset')
            ->get()
            ->map(fn (LedgerAccount $account): array => [
                'coin' => $account->asset,
                'name' => $account->asset,
                'depositAllEnable' => true,
                'withdrawAllEnable' => true,
                'free' => LedgerAmount::wire($account->free),
                'locked' => LedgerAmount::wire($account->locked),
                'isLegalMoney' => $account->asset === 'BRL',
                'trading' => true,
                'networkList' => array_map(
                    static fn (array $network): array => [...$network, 'coin' => $account->asset],
                    $networks,
                ),
            ])
            ->all();

        return array_values($coins);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function networks(): array
    {
        $fees = config()->array('fake-binance-withdraw.fees');
        $networks = [];
        $first = true;

        foreach ($fees as $network => $fee) {
            if (!is_string($fee) || !is_numeric($fee)) {
                throw MisconfiguredWithdrawFeeException::forNetwork((string) $network, is_string($fee) ? $fee : get_debug_type($fee));
            }

            $networks[] = [
                'network' => (string) $network,
                'name' => (string) $network,
                'isDefault' => $first,
                'depositEnable' => true,
                'withdrawEnable' => true,
                'withdrawFee' => $fee,
                'withdrawMin' => $fee,
                'withdrawMax' => '9999999999',
                'minConfirm' => 1,
                'unLockConfirm' => 0,
                'busy' => false,
                'estimatedArrivalTime' => 1,
                'addressRegex' => '',
                'memoRegex' => '',
                'specialTips' => '',
            ];

            $first = false;
        }

        return $networks;
    }
}
