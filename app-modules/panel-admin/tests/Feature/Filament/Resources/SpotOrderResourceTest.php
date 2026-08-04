<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Tests\Feature\Filament;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use He4rt\Identity\Permissions\Roles;
use He4rt\Identity\Users\User;
use He4rt\PanelAdmin\Filament\Resources\SpotOrders\Pages\ListSpotOrders;
use He4rt\Venue\Spot\Enums\OrderStatus;
use He4rt\Venue\Spot\Models\SpotOrder;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    actingAs(User::factory()->create());
    auth()->user()->assignRole(Roles::SuperAdmin->value);
});

it('can list spot orders', function (): void {
    $orders = SpotOrder::factory()->count(2)->create();

    livewire(ListSpotOrders::class)
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords($orders);
});

it('can reject an order', function (): void {
    $order = SpotOrder::factory()->create(['status' => OrderStatus::Filled]);

    livewire(ListSpotOrders::class)
        ->callAction(TestAction::make('reject')->table($order))
        ->assertNotified();

    expect($order->refresh()->status)->toBe(OrderStatus::Rejected);
});

it('can expire an order partially', function (): void {
    $order = SpotOrder::factory()->create([
        'status' => OrderStatus::Filled,
        'executed_qty' => '2',
        'cummulative_quote_qty' => '10',
        'commission' => '0.002',
    ]);

    livewire(ListSpotOrders::class)
        ->callAction(TestAction::make('expirePartially')->table($order))
        ->assertNotified();

    expect($order->refresh()->status)->toBe(OrderStatus::Expired);
});

it('can emit an unknown status', function (): void {
    $order = SpotOrder::factory()->create(['status' => OrderStatus::Filled]);

    livewire(ListSpotOrders::class)
        ->callAction(TestAction::make('emitUnknown')->table($order), ['rawStatus' => 'SOME_FUTURE_STATE'])
        ->assertNotified();

    expect($order->refresh()->raw_status_override)->toBe('SOME_FUTURE_STATE');
});
