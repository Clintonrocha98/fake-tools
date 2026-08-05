<?php

declare(strict_types=1);

use He4rt\FakeBinance\Fiat\Enums\FiatOrderStatus;
use He4rt\FakeBinance\Fiat\Enums\FiatStatusDialect;

/*
|--------------------------------------------------------------------------
| FiatOrderStatus::toWire()
|--------------------------------------------------------------------------
|
| Os dois dialetos que a Binance real fala: o default `Live` (SCREAMING_SNAKE
| observado ao vivo) e o `Classic` (doc pública). Ver ADR-0001.
|
*/

it('serializes every case in the live (ORDER_*) dialect', function (FiatOrderStatus $status, string $wire): void {
    expect($status->toWire(FiatStatusDialect::Live))->toBe($wire);
})->with([
    [FiatOrderStatus::Processing, 'ORDER_PROCESSING'],
    [FiatOrderStatus::NeedAdditionalAction, 'ORDER_NEED_ADDITIONAL_ACTION'],
    [FiatOrderStatus::Success, 'ORDER_SUCCESS'],
    [FiatOrderStatus::Completed, 'ORDER_COMPLETED'],
    [FiatOrderStatus::Failed, 'ORDER_FAILED'],
    [FiatOrderStatus::Expired, 'ORDER_EXPIRED'],
    [FiatOrderStatus::Cancelled, 'ORDER_CANCELLED'],
    [FiatOrderStatus::Refunding, 'ORDER_REFUNDING'],
    [FiatOrderStatus::Refunded, 'ORDER_REFUNDED'],
    [FiatOrderStatus::RefundFailed, 'ORDER_REFUND_FAILED'],
    [FiatOrderStatus::PartialCreditStopped, 'ORDER_PARTIAL_CREDIT_STOPPED'],
]);

it('serializes every case in the classic (documented) dialect', function (FiatOrderStatus $status, string $wire): void {
    expect($status->toWire(FiatStatusDialect::Classic))->toBe($wire);
})->with([
    [FiatOrderStatus::Processing, 'Processing'],
    [FiatOrderStatus::NeedAdditionalAction, 'Processing'],
    [FiatOrderStatus::Success, 'Successful'],
    [FiatOrderStatus::Completed, 'Finished'],
    [FiatOrderStatus::Failed, 'Failed'],
    [FiatOrderStatus::Expired, 'Expired'],
    [FiatOrderStatus::Cancelled, 'Failed'],
    [FiatOrderStatus::Refunding, 'Refunding'],
    [FiatOrderStatus::Refunded, 'Refunded'],
    [FiatOrderStatus::RefundFailed, 'Refund Failed'],
    [FiatOrderStatus::PartialCreditStopped, 'Order Partial Credit Stopped'],
]);

it('only Success and Completed are credited', function (FiatOrderStatus $status): void {
    expect($status->isCredited())->toBe(in_array($status, [FiatOrderStatus::Success, FiatOrderStatus::Completed], strict: true));
})->with(FiatOrderStatus::cases());

it('implements the Filament enum contracts exhaustively for every case', function (FiatOrderStatus $status): void {
    expect($status->getLabel())->toBeString()->not->toBeEmpty()
        ->and($status->getColor())->toBeString()->not->toBeEmpty()
        ->and($status->getDescription())->toBeString()->not->toBeEmpty();
})->with(FiatOrderStatus::cases());

it('implements the Filament enum contracts exhaustively for every dialect', function (FiatStatusDialect $dialect): void {
    expect($dialect->getLabel())->toBeString()->not->toBeEmpty()
        ->and($dialect->getColor())->toBeString()->not->toBeEmpty()
        ->and($dialect->getDescription())->toBeString()->not->toBeEmpty();
})->with(FiatStatusDialect::cases());
