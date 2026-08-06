<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Brcode\Http\Controllers;

use He4rt\FakeStarkbank\Brcode\Actions\PreviewBrcode;
use He4rt\FakeStarkbank\Brcode\Exceptions\MalformedBrcodeException;
use He4rt\FakeStarkbank\Http\Errors\ErrorResponseFactory;
use He4rt\FakeStarkbank\Http\Errors\StarkbankErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /v2/brcode-preview?brcodes={brcode}` — envelope plural `previews`, que o
 * consumidor lê posicionalmente em `previews.0`.
 *
 * O parâmetro é plural no provedor (aceita uma lista separada por vírgula), mas
 * o consumidor manda sempre UM código e o fake trata a query inteira como um
 * único brcode. Fatiar por vírgula quebraria um copia-e-cola que contivesse
 * uma — o valor viaja URL-encoded e é parte da mensagem assinada.
 */
final readonly class PreviewBrcodeController
{
    public function __construct(
        private PreviewBrcode $previewBrcode,
        private ErrorResponseFactory $errors,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $brcode = $request->query('brcodes');

        if (!is_string($brcode) || $brcode === '') {
            return $this->errors->make(
                StarkbankErrorCode::InvalidRequest,
                'Missing parameters in query: brcodes',
            );
        }

        try {
            $preview = $this->previewBrcode->handle($brcode);
        } catch (MalformedBrcodeException $malformedBrcodeException) {
            return $this->errors->make(StarkbankErrorCode::InvalidBrcode, $malformedBrcodeException->getMessage());
        }

        return response()->json(['previews' => [$preview]]);
    }
}
