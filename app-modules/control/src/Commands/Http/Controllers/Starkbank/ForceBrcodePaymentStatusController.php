<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Starkbank;

use He4rt\Control\Commands\Http\Requests\ForceStatusRequest;
use He4rt\Control\Http\WireEnum;
use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeStarkbank\Brcode\Actions\ForceBrcodePaymentStatus;
use He4rt\FakeStarkbank\Brcode\Enums\BrcodePaymentStatus;
use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/starkbank/brcode-payments/{brcodePayment}/force`.
 */
final readonly class ForceBrcodePaymentStatusController
{
    public function __construct(private ForceBrcodePaymentStatus $force) {}

    public function __invoke(ForceStatusRequest $request, BrcodePayment $brcodePayment): JsonResponse
    {
        $status = WireEnum::resolve(BrcodePaymentStatus::class, $request->status());

        return response()->json([
            'brcodePayment' => ResourceRows::brcodePayment($this->force->handle($brcodePayment, $status))->toArray(),
        ]);
    }
}
