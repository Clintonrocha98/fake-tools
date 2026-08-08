<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Starkbank;

use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeStarkbank\Brcode\Actions\AdvanceBrcodePaymentStatus;
use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/starkbank/brcode-payments/{brcodePayment}/advance`.
 */
final readonly class AdvanceBrcodePaymentController
{
    public function __construct(private AdvanceBrcodePaymentStatus $advance) {}

    public function __invoke(BrcodePayment $brcodePayment): JsonResponse
    {
        return response()->json([
            'brcodePayment' => ResourceRows::brcodePayment($this->advance->handle($brcodePayment))->toArray(),
        ]);
    }
}
