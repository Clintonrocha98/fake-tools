<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Tests\Feature\Filament;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use He4rt\FakeBinance\Ledger\Models\LedgerAccount;
use He4rt\Identity\Permissions\Roles;
use He4rt\Identity\Users\User;
use He4rt\PanelAdmin\Filament\Resources\LedgerAccounts\Pages\ListLedgerAccounts;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    actingAs(User::factory()->create());
    auth()->user()->assignRole(Roles::SuperAdmin->value);
});

it('can list ledger accounts', function (): void {
    $accounts = LedgerAccount::factory()->count(2)->create();

    livewire(ListLedgerAccounts::class)
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords($accounts);
});

it('can set a new asset balance from the header action', function (): void {
    livewire(ListLedgerAccounts::class)
        ->callAction(TestAction::make('setNewAsset')->table(), ['asset' => 'BRL', 'free' => '1000', 'locked' => '50'])
        ->assertNotified();

    $account = LedgerAccount::query()->where('asset', 'BRL')->firstOrFail();
    expect((string) $account->free)->toBe('1000.000000000000000000')
        ->and((string) $account->locked)->toBe('50.000000000000000000');
});

it('can edit an existing balance', function (): void {
    $account = LedgerAccount::factory()->create(['asset' => 'USDC', 'free' => '1', 'locked' => '0']);

    livewire(ListLedgerAccounts::class)
        ->callAction(TestAction::make('editBalance')->table($account), ['free' => '999', 'locked' => '1'])
        ->assertNotified();

    expect((string) $account->refresh()->free)->toBe('999.000000000000000000')
        ->and((string) $account->locked)->toBe('1.000000000000000000');
});
