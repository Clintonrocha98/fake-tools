<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Deposit\Http\Controllers;

use He4rt\FakeBinance\Deposit\Actions\GetDepositAddress;
use He4rt\FakeBinance\Deposit\Exceptions\UnsupportedDepositNetworkException;
use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;
use He4rt\FakeBinance\Http\Errors\ErrorFamily;
use He4rt\FakeBinance\Http\Errors\ErrorResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /sapi/v1/capital/deposit/address — assinado (`fake-binance.signed`).
 * `coin` é mandatório; `network` opcional cai na rede default do mapa de
 * endereços, como a venue real cai na rede default da coin.
 */
final readonly class DepositAddressController
{
    public function __construct(
        private GetDepositAddress $address,
        private ErrorResponseFactory $errors,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $family = ErrorFamily::fromPath($request->path());

        $coin = $request->query('coin');
        $network = $request->query('network');

        if (!is_string($coin) || $coin === '') {
            return $this->errors->make($family, BinanceErrorCode::MandatoryParameterMissing);
        }

        try {
            $payload = $this->address->handle($coin, is_string($network) && $network !== '' ? $network : null);
        } catch (UnsupportedDepositNetworkException $unsupportedDepositNetworkException) {
            return $this->errors->make($family, BinanceErrorCode::MandatoryParameterMissing, $unsupportedDepositNetworkException->getMessage());
        }

        return response()->json($payload);
    }
}
