<?php

declare(strict_types=1);

use He4rt\Venue\Fiat\Enums\FiatOrderStatus;
use He4rt\Venue\Fiat\Enums\FiatStatusDialect;

/*
|--------------------------------------------------------------------------
| FiatOrderStatus::toWire()
|--------------------------------------------------------------------------
|
| Os dois dialetos que a Binance real fala (ticket #4): o default `Live`
| (SCREAMING_SNAKE observado ao vivo) e o `Classic` (doc pública).
|
*/

it('serializes every case in the live (ORDER_*) dialect', function (FiatOrderStatus $status, string $wire): void {
    expect($status->toWire(FiatStatusDialect::Live))->toBe($wire);
})->with([
    [FiatOrderStatus::Processing, 'ORDER_PROCESSING'],
    [FiatOrderStatus::NeedAdditionalAction, 'ORDER_NEED_ADDITIONAL_ACTION'],
    [FiatOrderStatus::Success, 'ORDER_SUCCESS'],
    [FiatOrderStatus::Failed, 'ORDER_FAILED'],
    [FiatOrderStatus::Expired, 'ORDER_EXPIRED'],
    [FiatOrderStatus::Cancelled, 'ORDER_CANCELLED'],
    [FiatOrderStatus::Refunding, 'ORDER_REFUNDING'],
    [FiatOrderStatus::Refunded, 'ORDER_REFUNDED'],
]);

it('serializes every case in the classic (documented) dialect', function (FiatOrderStatus $status, string $wire): void {
    expect($status->toWire(FiatStatusDialect::Classic))->toBe($wire);
})->with([
    [FiatOrderStatus::Processing, 'Processing'],
    [FiatOrderStatus::NeedAdditionalAction, 'Processing'],
    [FiatOrderStatus::Success, 'Successful'],
    [FiatOrderStatus::Failed, 'Failed'],
    [FiatOrderStatus::Expired, 'Expired'],
    [FiatOrderStatus::Cancelled, 'Failed'],
    [FiatOrderStatus::Refunding, 'Refunding'],
    [FiatOrderStatus::Refunded, 'Refunded'],
]);

it('only Success is credited', function (FiatOrderStatus $status): void {
    expect($status->isCredited())->toBe($status === FiatOrderStatus::Success);
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
