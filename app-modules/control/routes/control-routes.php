<?php

declare(strict_types=1);

use He4rt\Control\Commands\Http\Controllers\Binance\AdvanceCryptoDepositController;
use He4rt\Control\Commands\Http\Controllers\Binance\AdvanceWithdrawController;
use He4rt\Control\Commands\Http\Controllers\Binance\AnnounceCryptoDepositController;
use He4rt\Control\Commands\Http\Controllers\Binance\CompleteWithdrawNowController;
use He4rt\Control\Commands\Http\Controllers\Binance\CreditFiatOrderController;
use He4rt\Control\Commands\Http\Controllers\Binance\CreditLedgerAccountController;
use He4rt\Control\Commands\Http\Controllers\Binance\DebitLedgerAccountController;
use He4rt\Control\Commands\Http\Controllers\Binance\DelayFiatBrcodeController;
use He4rt\Control\Commands\Http\Controllers\Binance\EmitUnknownFiatWireStatusController;
use He4rt\Control\Commands\Http\Controllers\Binance\EmitUnknownSpotOrderStatusController;
use He4rt\Control\Commands\Http\Controllers\Binance\EmitUnknownWithdrawStatusController;
use He4rt\Control\Commands\Http\Controllers\Binance\ExpireSpotOrderPartiallyController;
use He4rt\Control\Commands\Http\Controllers\Binance\ForceFiatOrderStatusController;
use He4rt\Control\Commands\Http\Controllers\Binance\ForceWithdrawStatusController;
use He4rt\Control\Commands\Http\Controllers\Binance\RejectSpotOrderController;
use He4rt\Control\Commands\Http\Controllers\Binance\SetFiatOrderFrozenController;
use He4rt\Control\Commands\Http\Controllers\Binance\SetLedgerBalanceController;
use He4rt\Control\Commands\Http\Controllers\Binance\SetWithdrawFrozenController;
use He4rt\Control\Commands\Http\Controllers\Starkbank\AdvanceBrcodePaymentController;
use He4rt\Control\Commands\Http\Controllers\Starkbank\AdvanceInvoiceController;
use He4rt\Control\Commands\Http\Controllers\Starkbank\AdvanceTransferController;
use He4rt\Control\Commands\Http\Controllers\Starkbank\EmitCorruptedEmissionController;
use He4rt\Control\Commands\Http\Controllers\Starkbank\ForceBrcodePaymentStatusController;
use He4rt\Control\Commands\Http\Controllers\Starkbank\ForceInvoiceStatusController;
use He4rt\Control\Commands\Http\Controllers\Starkbank\ForceTransferStatusController;
use He4rt\Control\Commands\Http\Controllers\Starkbank\RegisterDictKeyController;
use He4rt\Control\Commands\Http\Controllers\Starkbank\ReleaseEmissionHoldController;
use He4rt\Control\Commands\Http\Controllers\Starkbank\ReplayEmissionController;
use He4rt\Control\Commands\Http\Controllers\Starkbank\SetInvoiceFrozenController;
use He4rt\Control\Feed\Http\Controllers\ReadControlFeedController;
use He4rt\Control\Http\RegistersControlRouteBindings;
use He4rt\Control\Reset\Http\Controllers\ResetBaselineController;
use He4rt\Control\Scenarios\Http\Controllers\ArmScenarioController;
use He4rt\Control\Scenarios\Http\Controllers\DisarmScenarioController;
use He4rt\Control\Scenarios\Http\Controllers\GetScenarioSwitchboardController;
use He4rt\Control\Scenarios\Http\Controllers\ListScenariosController;
use He4rt\Control\Scenarios\Http\Controllers\ToggleScenarioSwitchController;
use He4rt\Control\State\Http\Controllers\GetControlStateController;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Plano de controle
|--------------------------------------------------------------------------
|
| O grupo NÃO leva nenhum middleware de fake, e cada ausência é deliberada:
|
| - `*.request-log`        o polling do feed geraria evento no próprio feed
| - `*.scenario-switches`  cenário armado é para o consumidor consumir, não
|                          para o operador tropeçar
| - `*.signed`             nos fakes a verificação de assinatura É o produto;
|                          aqui seria atrito sem fidelidade a ganhar
|
| Sem autenticação e sem CORS: consumo server-to-server (o Livewire do monolito
| chama por 127.0.0.1:8080) e este repo é ferramenta de dev que não vai a
| produção.
|
| Com o kill-switch desligado as rotas NEM REGISTRAM — 404, não 403. Um grupo
| registrado e barrado por middleware continuaria aparecendo em `route:list`.
|
*/

if (!config('control.enabled')) {
    return;
}

RegistersControlRouteBindings::register(resolve(Router::class));

Route::middleware('api')->prefix('control')->group(static function (): void {
    Route::get('/feed', ReadControlFeedController::class);
    Route::get('/state', GetControlStateController::class);
    Route::post('/reset', ResetBaselineController::class);

    /*
    | Cenários — `{fake}` é `starkbank` ou `binance`, e a bridge do segmento
    | decide qual dos dois conjuntos de Actions gêmeas responde. Nada passa a
    | ser compartilhado entre os fakes por causa disto.
    */
    Route::prefix('{fake}')->group(static function (): void {
        Route::get('/scenarios', ListScenariosController::class);
        Route::post('/scenarios', ArmScenarioController::class);
        Route::delete('/scenarios/{leg}', DisarmScenarioController::class);
        Route::get('/switchboard', GetScenarioSwitchboardController::class);
        Route::post('/switchboard', ToggleScenarioSwitchController::class);
    });

    /*
    | Comandos da malha PIX — um POST por gesto, cada um invocando a mesma
    | Action que o Resource do painel invoca.
    */
    Route::prefix('starkbank')->group(static function (): void {
        Route::post('/invoices/{invoice}/advance', AdvanceInvoiceController::class);
        Route::post('/invoices/{invoice}/force', ForceInvoiceStatusController::class);
        Route::post('/invoices/{invoice}/freeze', SetInvoiceFrozenController::class);

        Route::post('/transfers/{transfer}/advance', AdvanceTransferController::class);
        Route::post('/transfers/{transfer}/force', ForceTransferStatusController::class);

        Route::post('/brcode-payments/{brcodePayment}/advance', AdvanceBrcodePaymentController::class);
        Route::post('/brcode-payments/{brcodePayment}/force', ForceBrcodePaymentStatusController::class);

        Route::post('/emissions/{emission}/replay', ReplayEmissionController::class);
        Route::post('/emissions/{emission}/release', ReleaseEmissionHoldController::class);
        Route::post('/emissions/{emission}/emit-corrupted', EmitCorruptedEmissionController::class);

        Route::post('/dict-entries', RegisterDictKeyController::class);
    });

    /*
    | Comandos da venue.
    */
    Route::prefix('binance')->group(static function (): void {
        Route::post('/fiat-orders/{fiatOrder}/credit', CreditFiatOrderController::class);
        Route::post('/fiat-orders/{fiatOrder}/force', ForceFiatOrderStatusController::class);
        Route::post('/fiat-orders/{fiatOrder}/freeze', SetFiatOrderFrozenController::class);
        Route::post('/fiat-orders/{fiatOrder}/delay-brcode', DelayFiatBrcodeController::class);
        Route::post('/fiat-orders/{fiatOrder}/emit-unknown-status', EmitUnknownFiatWireStatusController::class);

        Route::post('/spot-orders/{spotOrder}/expire-partially', ExpireSpotOrderPartiallyController::class);
        Route::post('/spot-orders/{spotOrder}/reject', RejectSpotOrderController::class);
        Route::post('/spot-orders/{spotOrder}/emit-unknown-status', EmitUnknownSpotOrderStatusController::class);

        Route::post('/withdrawals/{withdrawal}/advance', AdvanceWithdrawController::class);
        Route::post('/withdrawals/{withdrawal}/complete', CompleteWithdrawNowController::class);
        Route::post('/withdrawals/{withdrawal}/force', ForceWithdrawStatusController::class);
        Route::post('/withdrawals/{withdrawal}/freeze', SetWithdrawFrozenController::class);
        Route::post('/withdrawals/{withdrawal}/emit-unknown-status', EmitUnknownWithdrawStatusController::class);

        Route::post('/ledger/{asset}/set', SetLedgerBalanceController::class);
        Route::post('/ledger/{asset}/credit', CreditLedgerAccountController::class);
        Route::post('/ledger/{asset}/debit', DebitLedgerAccountController::class);

        Route::post('/crypto-deposits', AnnounceCryptoDepositController::class);
        Route::post('/crypto-deposits/{cryptoDeposit}/advance', AdvanceCryptoDepositController::class);
    });
});
