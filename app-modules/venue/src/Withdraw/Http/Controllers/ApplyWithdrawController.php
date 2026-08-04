<?php

declare(strict_types=1);

namespace He4rt\Venue\Withdraw\Http\Controllers;

use He4rt\Venue\Http\Errors\BinanceErrorCode;
use He4rt\Venue\Http\Errors\ErrorFamily;
use He4rt\Venue\Http\Errors\VenueErrorResponseFactory;
use He4rt\Venue\Ledger\Exceptions\InsufficientLedgerBalanceException;
use He4rt\Venue\Withdraw\Actions\ApplyWithdraw;
use He4rt\Venue\Withdraw\DTOs\ApplyWithdrawData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /sapi/v1/capital/withdraw/apply — tudo assinado na query (`venue.signed`).
 * Devolve só `{"id": ...}`, o único campo que `BinanceVenueWithdrawGateway::withdraw()`
 * lê da resposta real.
 */
final readonly class ApplyWithdrawController
{
    public function __construct(
        private ApplyWithdraw $apply,
        private VenueErrorResponseFactory $errors,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $family = ErrorFamily::fromPath($request->path());

        $coin = $this->stringOrNull($request, 'coin');
        $address = $this->stringOrNull($request, 'address');
        $amount = $this->stringOrNull($request, 'amount');
        $network = $this->stringOrNull($request, 'network');

        if ($coin === null || $address === null || $amount === null || $network === null || !is_numeric($amount)) {
            return $this->errors->make($family, BinanceErrorCode::MandatoryParameterMissing);
        }

        $data = new ApplyWithdrawData(
            coin: $coin,
            address: $address,
            amount: $amount,
            network: $network,
            withdrawOrderId: $this->stringOrNull($request, 'withdrawOrderId'),
            addressTag: $this->stringOrNull($request, 'addressTag'),
        );

        try {
            $withdrawal = ($this->apply)($data);
        } catch (InsufficientLedgerBalanceException) {
            return $this->errors->make($family, BinanceErrorCode::NewOrderRejected);
        }

        return response()->json(['id' => $withdrawal->id]);
    }

    private function stringOrNull(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
