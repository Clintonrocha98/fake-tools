<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Binance;

use He4rt\Control\Commands\Http\Requests\LedgerAmountRequest;
use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeBinance\Ledger\Actions\CreditLedgerAccount;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/binance/ledger/{asset}/credit` — soma um delta.
 */
final readonly class CreditLedgerAccountController
{
    public function __construct(private CreditLedgerAccount $credit) {}

    public function __invoke(LedgerAmountRequest $request, string $asset): JsonResponse
    {
        $conta = $this->credit->handle($asset, $request->amount());

        return response()->json(['ledgerAccount' => ResourceRows::ledgerAccount($conta)->toArray()]);
    }
}
