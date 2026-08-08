<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Starkbank;

use He4rt\Control\Commands\Http\Requests\RegisterDictKeyRequest;
use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeStarkbank\Dict\Actions\RegisterDictKey;
use He4rt\FakeStarkbank\Dict\DTOs\RegisterDictKeyData;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/starkbank/dict-entries` — sem uma chave registrada o cash-out
 * não tem beneficiário.
 */
final readonly class RegisterDictKeyController
{
    public function __construct(private RegisterDictKey $register) {}

    public function __invoke(RegisterDictKeyRequest $request): JsonResponse
    {
        $entrada = $this->register->handle(RegisterDictKeyData::fromForm($request->toDictForm()));

        return response()->json(['dictEntry' => ResourceRows::dictEntry($entrada)->toArray()], 201);
    }
}
