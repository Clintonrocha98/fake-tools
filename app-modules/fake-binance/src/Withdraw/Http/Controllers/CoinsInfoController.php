<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Withdraw\Http\Controllers;

use He4rt\FakeBinance\Withdraw\Actions\GetCoinsInfo;
use Illuminate\Http\JsonResponse;

/**
 * GET /sapi/v1/capital/config/getall — assinado (`fake-binance.signed`), sem
 * parâmetros de negócio.
 */
final readonly class CoinsInfoController
{
    public function __construct(private GetCoinsInfo $coinsInfo) {}

    public function __invoke(): JsonResponse
    {
        return response()->json($this->coinsInfo->handle());
    }
}
