<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Starkbank;

use He4rt\Control\Commands\Http\Requests\ForceStatusRequest;
use He4rt\Control\Http\WireEnum;
use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeStarkbank\Transfer\Actions\ForceTransferStatus;
use He4rt\FakeStarkbank\Transfer\Enums\TransferStatus;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/starkbank/transfers/{transfer}/force`.
 */
final readonly class ForceTransferStatusController
{
    public function __construct(private ForceTransferStatus $force) {}

    public function __invoke(ForceStatusRequest $request, Transfer $transfer): JsonResponse
    {
        $status = WireEnum::resolve(TransferStatus::class, $request->status());

        return response()->json([
            'transfer' => ResourceRows::transfer($this->force->handle($transfer, $status))->toArray(),
        ]);
    }
}
