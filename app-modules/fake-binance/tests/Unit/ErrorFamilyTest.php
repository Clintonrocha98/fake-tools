<?php

declare(strict_types=1);

use He4rt\FakeBinance\Http\Errors\ErrorFamily;

/*
|--------------------------------------------------------------------------
| ErrorFamily::fromPath()
|--------------------------------------------------------------------------
|
| Cada path abaixo é um endpoint `Signed` real que o monolito consumidor
| chama hoje (`integration-binance/src/Http/Requests/*::resolveEndpoint()`).
| Este dataset é o único ponto que trava a classificação de família contra
| a superfície real — um novo consumer route só pode ser classificado
| errado se este dataset também for atualizado errado.
|
*/

it('classifies every fiat path the monolith calls, across versions, as Fiat', function (string $path): void {
    expect(ErrorFamily::fromPath($path))->toBe(ErrorFamily::Fiat);
})->with([
    'sapi/v1/fiat/deposit',
    'sapi/v1/fiat/get-order-detail',
    'sapi/v2/fiat/withdraw',
]);

it('classifies every non-fiat signed path the monolith calls as SpotWallet', function (string $path): void {
    expect(ErrorFamily::fromPath($path))->toBe(ErrorFamily::SpotWallet);
})->with([
    'sapi/v1/localentity/questionnaire-requirements',
    'api/v3/myTrades',
    'sapi/v1/capital/withdraw/history',
    'api/v3/account',
    'sapi/v1/capital/withdraw/apply',
    'sapi/v1/capital/deposit/address',
    'sapi/v1/capital/deposit/hisrec',
    'api/v3/order',
    'sapi/v1/capital/config/getall',
]);
