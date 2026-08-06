<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Dict\Http\Controllers;

use He4rt\FakeStarkbank\Dict\Actions\ResolveDictKey;
use He4rt\FakeStarkbank\Dict\DTOs\DictKeyView;
use He4rt\FakeStarkbank\Dict\Exceptions\DictKeyNotFoundException;
use He4rt\FakeStarkbank\Http\Errors\ErrorResponseFactory;
use He4rt\FakeStarkbank\Http\Errors\StarkbankErrorCode;
use Illuminate\Http\JsonResponse;

/**
 * `GET /v2/dict-key/{key}` — envelope singular `{"key": {...}}`. A chave chega
 * URL-encoded no path (`ada%40brd.digital`) e o Laravel já a devolve decodificada.
 */
final readonly class ResolveDictKeyController
{
    public function __construct(
        private ResolveDictKey $resolveDictKey,
        private ErrorResponseFactory $errors,
    ) {}

    public function __invoke(string $key): JsonResponse
    {
        try {
            $entry = $this->resolveDictKey->handle($key);
        } catch (DictKeyNotFoundException) {
            return $this->errors->make(StarkbankErrorCode::InvalidDictKey);
        }

        return response()->json(['key' => DictKeyView::fromModel($entry)]);
    }
}
