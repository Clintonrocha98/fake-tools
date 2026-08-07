<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Withdraw\Http\Controllers;

use He4rt\FakeBinance\Support\BinanceLog;
use Illuminate\Http\JsonResponse;

/**
 * GET /sapi/v1/localentity/questionnaire-requirements — assinado
 * (`fake-binance.signed`), sem estado, um campo de resposta. O consumidor
 * consulta este gate fail-closed em `SendWalletWithdraw` imediatamente antes
 * de todo saque de entrega em carteira: qualquer resposta que não seja
 * `null`/`NIL` recusa a entrega antes de mover dinheiro; falha de transporte
 * também é recusa — por isso a rota precisa existir para o happy path passar.
 */
final readonly class QuestionnaireRequirementsController
{
    public function __invoke(): JsonResponse
    {
        $configured = config('fake-binance.travel_rule_questionnaire_country');

        $country = is_string($configured) && mb_trim($configured) !== '' ? mb_trim($configured) : null;

        BinanceLog::info('fake-binance.withdraw: travel rule consultado — o consumidor recusa a entrega em carteira para qualquer valor fora de null/NIL', [
            'questionnaire_country_code' => $country,
        ]);

        return response()->json(['questionnaireCountryCode' => $country]);
    }
}
