<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Binance;

use He4rt\Control\Commands\Http\Requests\SetLedgerBalanceRequest;
use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeBinance\Ledger\Actions\SetLedgerBalance;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/binance/ledger/{asset}/set` — atribui o saldo, não soma um
 * delta. Um asset que ainda não existe é criado pela Action, como no painel.
 */
final readonly class SetLedgerBalanceController
{
    public function __construct(private SetLedgerBalance $set) {}

    public function __invoke(SetLedgerBalanceRequest $request, string $asset): JsonResponse
    {
        $conta = $this->set->handle($asset, $request->free(), $request->locked());

        return response()->json(['ledgerAccount' => ResourceRows::ledgerAccount($conta)->toArray()]);
    }
}
