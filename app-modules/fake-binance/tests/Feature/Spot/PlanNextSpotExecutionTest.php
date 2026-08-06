<?php

declare(strict_types=1);

use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;
use He4rt\FakeBinance\Scenarios\Actions\ArmScenario;
use He4rt\FakeBinance\Scenarios\DTOs\ArmedScenarioPayload;
use He4rt\FakeBinance\Scenarios\Enums\SpotConversionOutcome;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;
use He4rt\FakeBinance\Spot\Actions\PlanNextSpotExecution;
use He4rt\FakeBinance\Spot\DTOs\SpotExecutionPlan;
use He4rt\FakeBinance\Spot\Enums\OrderStatus;

it('plans the happy path when nothing is armed', function (): void {
    $plan = resolve(PlanNextSpotExecution::class)->handle();

    expect($plan->fillFraction)->toBe('1')
        ->and($plan->finalStatus)->toBe(OrderStatus::Filled)
        ->and($plan->refuses())->toBeFalse()
        ->and($plan->fillsNothing())->toBeFalse()
        ->and($plan->rawStatusOverride)->toBeNull();
});

it('plans a partial fill that expires, using the armed fraction', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::FillPartialExpired, new ArmedScenarioPayload(fraction: '0.25'));

    $plan = resolve(PlanNextSpotExecution::class)->handle();

    expect($plan->fillFraction)->toBe('0.25')
        ->and($plan->finalStatus)->toBe(OrderStatus::Expired)
        ->and($plan->refuses())->toBeFalse();
});

it('falls back to half when the partial outcome was armed without a fraction', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::FillPartialExpired);

    expect(resolve(PlanNextSpotExecution::class)->handle()->fillFraction)
        ->toBe(SpotExecutionPlan::DEFAULT_PARTIAL_FRACTION);
});

it('plans a refusal with the armed code', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::RefuseWithCode, new ArmedScenarioPayload(errorCode: -1_013));

    $plan = resolve(PlanNextSpotExecution::class)->handle();

    expect($plan->refuses())->toBeTrue()
        ->and($plan->refusal)->toBe(BinanceErrorCode::FilterFailure);
});

it('falls back to NEW_ORDER_REJECTED when the refusal was armed without a code', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::RefuseWithCode);

    expect(resolve(PlanNextSpotExecution::class)->handle()->refusal)
        ->toBe(BinanceErrorCode::NewOrderRejected);
});

it('refuses to emit a code of another error family, falling back to NEW_ORDER_REJECTED', function (): void {
    // -16009 é fiat: responde HTTP 200 no envelope fiat, e sairia errado dentro
    // de /api/v3.
    (new ArmScenario)->handle(SpotConversionOutcome::RefuseWithCode, new ArmedScenarioPayload(errorCode: -16_009));

    expect(resolve(PlanNextSpotExecution::class)->handle()->refusal)
        ->toBe(BinanceErrorCode::NewOrderRejected);
});

it('plans a REJECTED response that fills nothing', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::RespondRejected);

    $plan = resolve(PlanNextSpotExecution::class)->handle();

    expect($plan->finalStatus)->toBe(OrderStatus::Rejected)
        ->and($plan->fillsNothing())->toBeTrue()
        ->and($plan->refuses())->toBeFalse();
});

it('plans an unknown wire status over an otherwise normal fill', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::EmitUnknownStatus, new ArmedScenarioPayload(rawStatus: 'BANANA'));

    $plan = resolve(PlanNextSpotExecution::class)->handle();

    expect($plan->rawStatusOverride)->toBe('BANANA')
        ->and($plan->finalStatus)->toBe(OrderStatus::Filled)
        ->and($plan->fillFraction)->toBe('1');
});

it('falls back to a placeholder status when the unknown-status outcome was armed without one', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::EmitUnknownStatus);

    expect(resolve(PlanNextSpotExecution::class)->handle()->rawStatusOverride)
        ->toBe(SpotExecutionPlan::DEFAULT_UNKNOWN_RAW_STATUS)
        ->and(OrderStatus::tryFrom(SpotExecutionPlan::DEFAULT_UNKNOWN_RAW_STATUS))->toBeNull();
});

it('falls back to the neutral plan when the stored outcome is not one of the leg', function (): void {
    ArmedScenario::factory()->create(['outcome' => 'fill_partial_and_dance']);

    $plan = resolve(PlanNextSpotExecution::class)->handle();

    // O cenário obsoleto é consumido junto com o plano neutro: a linha sai do
    // caminho em vez de fazer todo pedido seguinte cair no mesmo buraco.
    expect($plan->finalStatus)->toBe(OrderStatus::Filled)
        ->and($plan->fillFraction)->toBe('1')
        ->and($plan->refuses())->toBeFalse()
        ->and(ArmedScenario::query()->count())->toBe(0);
});

it('consumes the armed scenario when it plans', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::RespondRejected);

    resolve(PlanNextSpotExecution::class)->handle();

    expect(ArmedScenario::query()->count())->toBe(0)
        ->and(resolve(PlanNextSpotExecution::class)->handle()->finalStatus)->toBe(OrderStatus::Filled);
});

it('applies the fraction at the given scale, and never multiplies on the neutral plan', function (): void {
    $partial = new SpotExecutionPlan('0.5', OrderStatus::Expired, refusal: null, rawStatusOverride: null);

    expect($partial->applyFraction('2.93000000', 8))->toBe('1.46500000')
        ->and(SpotExecutionPlan::neutral()->applyFraction('2.93000000', 8))->toBe('2.93000000');
});
