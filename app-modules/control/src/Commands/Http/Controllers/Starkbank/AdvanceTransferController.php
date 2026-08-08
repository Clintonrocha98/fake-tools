<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Starkbank;

use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeStarkbank\Transfer\Actions\AdvanceTransferStatus;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/starkbank/transfers/{transfer}/advance`.
 */
final readonly class AdvanceTransferController
{
    public function __construct(private AdvanceTransferStatus $advance) {}

    public function __invoke(Transfer $transfer): JsonResponse
    {
        return response()->json([
            'transfer' => ResourceRows::transfer($this->advance->handle($transfer))->toArray(),
        ]);
    }
}
