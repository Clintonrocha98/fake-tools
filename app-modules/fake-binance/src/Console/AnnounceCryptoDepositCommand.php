<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Console;

use He4rt\FakeBinance\Deposit\Actions\AnnounceCryptoDeposit;
use He4rt\FakeBinance\Deposit\Exceptions\UnsupportedDepositNetworkException;
use Illuminate\Console\Command;

/**
 * A bancada de semeadura da chegada de cripto (ADR-0003): a Dakota "transferiu"
 * e o operador anuncia a chegada por aqui — o depósito nasce Pending e o
 * hisrec/ledger fazem o resto pelo avanço lazy. Scriptável em dev, e a mesma
 * Action fica pronta para o painel usar.
 */
final class AnnounceCryptoDepositCommand extends Command
{
    protected $signature = 'fake-binance:announce-deposit
        {coin : Asset que chegou (ex.: USDC)}
        {amount : Quantidade, decimal-string (ex.: 100.5)}
        {network=SOL : Rede da chegada (uma das mapeadas em fake-binance-deposit.addresses)}
        {--tx-id= : txId externo; ausente gera um sintético}';

    protected $description = 'Anuncia uma chegada de stablecoin — o depósito nasce Pending e credita o ledger pelo avanço lazy do hisrec';

    public function handle(AnnounceCryptoDeposit $announce): int
    {
        $coin = $this->argument('coin');
        $amount = $this->argument('amount');
        $network = $this->argument('network');
        $txId = $this->option('tx-id');

        if (!is_numeric($amount) || bccomp($amount, '0', 18) <= 0) {
            $this->error('amount precisa ser uma decimal-string positiva.');

            return self::FAILURE;
        }

        if ($coin === '' || $network === '') {
            $this->error('coin e network são obrigatórios.');

            return self::FAILURE;
        }

        try {
            $deposit = $announce->handle($coin, $network, $amount, is_string($txId) && $txId !== '' ? $txId : null);
        } catch (UnsupportedDepositNetworkException $unsupportedDepositNetworkException) {
            $this->error($unsupportedDepositNetworkException->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Depósito anunciado: %s %s via %s (txId %s).', $deposit->amount, $deposit->coin, $deposit->network, $deposit->tx_id));
        $this->line('Nasce Pending (0); o hisrec avança e credita o ledger pelo relógio de fake-binance-deposit.advance_seconds.');

        return self::SUCCESS;
    }
}
