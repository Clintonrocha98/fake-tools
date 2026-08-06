<?php

declare(strict_types=1);

use He4rt\FakeBinance\Deposit\Enums\DepositStatus;

it('replicates exactly the documented deposit status ints of the hisrec', function (): void {
    $wireValues = array_map(fn (DepositStatus $case): int => $case->value, DepositStatus::cases());

    expect($wireValues)->toEqualCanonicalizing([0, 1, 6, 7, 8]);
});

it('only lets the lifecycle states advance automatically', function (): void {
    expect(DepositStatus::Pending->advancesAutomatically())->toBeTrue()
        ->and(DepositStatus::Credited->advancesAutomatically())->toBeTrue()
        ->and(DepositStatus::Success->advancesAutomatically())->toBeFalse()
        ->and(DepositStatus::WrongDeposit->advancesAutomatically())->toBeFalse()
        ->and(DepositStatus::WaitingUserConfirm->advancesAutomatically())->toBeFalse();
});
