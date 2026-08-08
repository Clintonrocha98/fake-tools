<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Withdraw\Http\Controllers;

use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;
use He4rt\FakeBinance\Http\Errors\ErrorFamily;
use He4rt\FakeBinance\Http\Errors\ErrorResponseFactory;
use He4rt\FakeBinance\Ledger\Exceptions\InsufficientLedgerBalanceException;
use He4rt\FakeBinance\Support\BinanceLog;
use He4rt\FakeBinance\Withdraw\Actions\ApplyWithdraw;
use He4rt\FakeBinance\Withdraw\DTOs\ApplyWithdrawData;
use He4rt\FakeBinance\Withdraw\Support\WithdrawWireId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /sapi/v1/capital/withdraw/apply — tudo assinado na query (`fake-binance.signed`).
 * Devolve só `{"id": ...}`, o único campo que `BinanceVenueWithdrawGateway::withdraw()`
 * lê da resposta real, no formato hex-32 da venue ({@see WithdrawWireId}).
 */
final readonly class ApplyWithdrawController
{
    public function __construct(
        private ApplyWithdraw $apply,
        private ErrorResponseFactory $errors,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $family = ErrorFamily::fromPath($request->path());

        $coin = $this->stringOrNull($request, 'coin');
        $address = $this->stringOrNull($request, 'address');
        $amount = $this->stringOrNull($request, 'amount');

        // `network` fica fora deste guard de propósito: a doc do apply o marca
        // como opcional e, omitido, a venue saca pela rede default da coin.
        if ($coin === null || $address === null || $amount === null || !is_numeric($amount)) {
            return $this->errors->make($family, BinanceErrorCode::MandatoryParameterMissing);
        }

        $data = new ApplyWithdrawData(
            coin: $coin,
            address: $address,
            amount: $amount,
            network: $this->stringOrNull($request, 'network'),
            withdrawOrderId: $this->stringOrNull($request, 'withdrawOrderId'),
            addressTag: $this->stringOrNull($request, 'addressTag'),
        );

        try {
            $withdrawal = $this->apply->handle($data);
        } catch (InsufficientLedgerBalanceException $insufficientLedgerBalanceException) {
            BinanceLog::warning('fake-binance.withdraw: apply recusado com -2010 — saldo insuficiente no ledger', [
                'coin' => $coin,
                'network' => $data->network,
                'amount' => $amount,
                'withdraw_order_id' => $data->withdrawOrderId,
                'reason' => $insufficientLedgerBalanceException->getMessage(),
            ]);

            return $this->errors->make($family, BinanceErrorCode::NewOrderRejected);
        }

        return response()->json(['id' => WithdrawWireId::for($withdrawal->id)]);
    }

    private function stringOrNull(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
