<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Binance;

use He4rt\Control\Commands\Http\Requests\LedgerAmountRequest;
use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeBinance\Ledger\Actions\DebitLedgerAccount;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/binance/ledger/{asset}/debit` — saldo insuficiente é a recusa
 * que a Action já lança, traduzida para 422 pelo middleware do grupo.
 */
final readonly class DebitLedgerAccountController
{
    public function __construct(private DebitLedgerAccount $debit) {}

    public function __invoke(LedgerAmountRequest $request, string $asset): JsonResponse
    {
        $conta = $this->debit->handle($asset, $request->amount());

        return response()->json(['ledgerAccount' => ResourceRows::ledgerAccount($conta)->toArray()]);
    }
}
