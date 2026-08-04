<?php

declare(strict_types=1);

use He4rt\Venue\Withdraw\Enums\WithdrawStatus;

it('maps every Binance status code to the documented case', function (): void {
    expect(WithdrawStatus::EmailSent->value)->toBe(0)
        ->and(WithdrawStatus::Cancelled->value)->toBe(1)
        ->and(WithdrawStatus::AwaitingApproval->value)->toBe(2)
        ->and(WithdrawStatus::Rejected->value)->toBe(3)
        ->and(WithdrawStatus::Processing->value)->toBe(4)
        ->and(WithdrawStatus::Failure->value)->toBe(5)
        ->and(WithdrawStatus::Completed->value)->toBe(6);
});

it('implements the Filament enum contracts for every case', function (WithdrawStatus $status): void {
    expect($status->getLabel())->toBeString()->not->toBeEmpty()
        ->and($status->getColor())->not->toBeEmpty()
        ->and($status->getDescription())->toBeString()->not->toBeEmpty();
})->with(WithdrawStatus::cases());
