<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Tests\Feature\Filament;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use He4rt\Identity\Permissions\Roles;
use He4rt\Identity\Users\User;
use He4rt\PanelAdmin\Filament\Resources\Withdrawals\Pages\ListWithdrawals;
use He4rt\Venue\Withdraw\Enums\WithdrawStatus;
use He4rt\Venue\Withdraw\Models\Withdrawal;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    actingAs(User::factory()->create());
    auth()->user()->assignRole(Roles::SuperAdmin->value);
});

it('can list withdrawals', function (): void {
    $withdrawals = Withdrawal::factory()->count(2)->create();

    livewire(ListWithdrawals::class)
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords($withdrawals);
});

it('can complete a withdrawal now', function (): void {
    $withdrawal = Withdrawal::factory()->create(['status' => WithdrawStatus::AwaitingApproval]);

    livewire(ListWithdrawals::class)
        ->callAction(TestAction::make('completeNow')->table($withdrawal))
        ->assertNotified();

    expect($withdrawal->refresh()->status)->toBe(WithdrawStatus::Completed)
        ->and($withdrawal->tx_id)->not->toBeNull();
});

it('can force a documented failure status with info', function (): void {
    $withdrawal = Withdrawal::factory()->create(['status' => WithdrawStatus::AwaitingApproval]);

    livewire(ListWithdrawals::class)
        ->callAction(TestAction::make('forceStatus')->table($withdrawal), [
            'status' => (string) WithdrawStatus::Rejected->value,
            'info' => 'bad address',
        ])
        ->assertNotified();

    expect($withdrawal->refresh()->status)->toBe(WithdrawStatus::Rejected)
        ->and($withdrawal->info)->toBe('bad address');
});

it('can emit an unknown status code', function (): void {
    $withdrawal = Withdrawal::factory()->create(['status' => WithdrawStatus::AwaitingApproval]);

    livewire(ListWithdrawals::class)
        ->callAction(TestAction::make('emitUnknown')->table($withdrawal), ['rawStatus' => '99'])
        ->assertNotified();

    expect($withdrawal->refresh()->raw_status_override)->toBe(99);
});

it('can toggle the frozen flag', function (): void {
    $withdrawal = Withdrawal::factory()->create();

    livewire(ListWithdrawals::class)
        ->callAction(TestAction::make('toggleFrozen')->table($withdrawal))
        ->assertNotified();

    expect($withdrawal->refresh()->frozen)->toBeTrue();
});
