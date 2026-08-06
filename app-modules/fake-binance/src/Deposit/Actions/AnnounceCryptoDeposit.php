<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Deposit\Actions;

use He4rt\FakeBinance\Deposit\Enums\DepositStatus;
use He4rt\FakeBinance\Deposit\Models\CryptoDeposit;
use He4rt\FakeBinance\Withdraw\Support\SyntheticTxId;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;

/**
 * Semeia uma chegada de stablecoin — o único movimento do fake que NÃO nasce
 * de um pedido do consumidor (a Dakota transferiu por fora). Nasce `Pending`
 * (status 0), listada pelo hisrec no endereço que
 * {@see GetDepositAddress} anuncia para a rede; o crédito no ledger só
 * acontece quando o avanço lazy a leva a `Credited`
 * ({@see AdvanceCryptoDepositStatus}, ADR-0003) — anunciar nunca move saldo.
 */
final readonly class AnnounceCryptoDeposit
{
    public function __construct(
        private GetDepositAddress $address = new GetDepositAddress,
    ) {}

    /**
     * @param  numeric-string  $amount
     */
    public function handle(string $coin, string $network, string $amount, ?string $txId = null): CryptoDeposit
    {
        $coin = mb_strtoupper($coin);
        $network = mb_strtoupper($network);
        $address = $this->address->handle($coin, $network);

        $deposit = CryptoDeposit::query()->create([
            'coin' => $coin,
            'network' => $network,
            'address' => $address['address'],
            'amount' => $amount,
            'tx_id' => $txId ?? SyntheticTxId::generate(),
            'status' => DepositStatus::Pending,
            'announced_at' => Date::now(),
        ]);

        Log::info('fake-binance.deposit: chegada de cripto anunciada — nasce Pending, o ledger só credita quando o avanço lazy chegar a Credited', [
            'deposit_id' => $deposit->id,
            'coin' => $coin,
            'network' => $network,
            'amount' => $amount,
            'tx_id' => $deposit->tx_id,
            'address' => $deposit->address,
        ]);

        return $deposit;
    }
}
