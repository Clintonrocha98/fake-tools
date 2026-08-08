<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Binance;

use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeBinance\Deposit\Actions\AdvanceCryptoDepositStatus;
use He4rt\FakeBinance\Deposit\Models\CryptoDeposit;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/binance/crypto-deposits/{cryptoDeposit}/advance` — é o avanço
 * que credita o ledger ao chegar em Credited.
 */
final readonly class AdvanceCryptoDepositController
{
    public function __construct(private AdvanceCryptoDepositStatus $advance) {}

    public function __invoke(CryptoDeposit $cryptoDeposit): JsonResponse
    {
        return response()->json([
            'cryptoDeposit' => ResourceRows::cryptoDeposit($this->advance->handle($cryptoDeposit))->toArray(),
        ]);
    }
}
