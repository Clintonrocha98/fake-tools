<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Tests\Feature\Filament;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use He4rt\FakeBinance\Fiat\Enums\FiatOrderStatus;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use He4rt\FakeBinance\Ledger\Models\LedgerAccount;
use He4rt\Identity\Permissions\Roles;
use He4rt\Identity\Users\User;
use He4rt\PanelAdmin\Filament\Resources\FiatOrders\Pages\ListFiatOrders;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    actingAs(User::factory()->create());
    auth()->user()->assignRole(Roles::SuperAdmin->value);
});

it('can list fiat orders', function (): void {
    $orders = FiatOrder::factory()->count(2)->create();

    livewire(ListFiatOrders::class)
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords($orders);
});

it('can credit an order now, skipping the lazy clock', function (): void {
    $order = FiatOrder::factory()->create([
        'status' => FiatOrderStatus::Processing,
        'currency' => 'BRL',
        'amount' => '500',
    ]);

    livewire(ListFiatOrders::class)
        ->callAction(TestAction::make('creditNow')->table($order))
        ->assertNotified();

    expect($order->refresh()->status)->toBe(FiatOrderStatus::Success);
    expect((string) LedgerAccount::query()->where('asset', 'BRL')->firstOrFail()->free)
        ->toBe('500.000000000000000000');
});

it('can force a documented failure status', function (): void {
    $order = FiatOrder::factory()->create(['status' => FiatOrderStatus::Processing]);

    livewire(ListFiatOrders::class)
        ->callAction(TestAction::make('forceStatus')->table($order), ['status' => FiatOrderStatus::Failed->value])
        ->assertNotified();

    expect($order->refresh()->forced_status)->toBe(FiatOrderStatus::Failed);
});

it('can emit an unknown wire status', function (): void {
    $order = FiatOrder::factory()->create(['status' => FiatOrderStatus::Processing]);

    livewire(ListFiatOrders::class)
        ->callAction(TestAction::make('emitUnknown')->table($order), ['wireStatus' => 'ORDER_FUTURE_STATE'])
        ->assertNotified();

    expect($order->refresh()->forced_wire_status)->toBe('ORDER_FUTURE_STATE');
});

it('can delay the brcode', function (): void {
    $order = FiatOrder::factory()->create();

    livewire(ListFiatOrders::class)
        ->callAction(TestAction::make('delayBrcode')->table($order), ['reads' => 3])
        ->assertNotified();

    expect($order->refresh()->brcode_delay_reads)->toBe(3);
});

it('can toggle the frozen flag', function (): void {
    $order = FiatOrder::factory()->create();

    livewire(ListFiatOrders::class)
        ->callAction(TestAction::make('toggleFrozen')->table($order))
        ->assertNotified();

    expect($order->refresh()->frozen)->toBeTrue();
});
