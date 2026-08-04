<?php

declare(strict_types=1);

use He4rt\Venue\Scenarios\Actions\ToggleScenarioSwitch;
use He4rt\Venue\Scenarios\Enums\ScenarioSwitch;

it('answers the happy path untouched when every switch is off', function (): void {
    $this->getJson('/api/v3/ticker/bookTicker?symbol=USDCBRL')->assertOk();
});

it('outage mode answers HTTP 5xx before any auth check, even on an unsigned request', function (): void {
    (new ToggleScenarioSwitch)(ScenarioSwitch::Outage, true);

    $response = $this->getJson('/api/v3/ticker/bookTicker?symbol=USDCBRL');

    $response->assertServerError()->assertExactJson([
        'code' => -1_001,
        'msg' => 'Internal error; unable to process your request. Please try again.',
    ]);
});

it('outage mode answers the fiat envelope on a fiat path', function (): void {
    (new ToggleScenarioSwitch)(ScenarioSwitch::Outage, true);

    $response = $this->getJson('/sapi/v1/fiat/get-order-detail?orderNo=anything');

    $response->assertStatus(503)->assertExactJson([
        'code' => '-1001',
        'message' => 'Internal error; unable to process your request. Please try again.',
        'success' => false,
        'data' => null,
    ]);
});

it('rate limit mode answers 429 with Retry-After, spot/wallet envelope, before any auth check', function (): void {
    (new ToggleScenarioSwitch)(ScenarioSwitch::RateLimit, true);

    $response = $this->getJson('/api/v3/account');

    $response->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertExactJson(['code' => -1_003, 'msg' => 'Way too many requests; please try again later.']);
});

it('rate limit mode answers the fiat envelope on a fiat path', function (): void {
    (new ToggleScenarioSwitch)(ScenarioSwitch::RateLimit, true);

    $response = $this->getJson('/sapi/v1/fiat/get-order-detail?orderNo=anything');

    $response->assertStatus(429)->assertExactJson([
        'code' => '-1003',
        'message' => 'Way too many requests; please try again later.',
        'success' => false,
        'data' => null,
    ]);
});

it('clock skew mode refuses everything with -1021, before any auth check', function (): void {
    (new ToggleScenarioSwitch)(ScenarioSwitch::ClockSkew, true);

    $response = $this->getJson('/api/v3/account');

    $response->assertStatus(400)->assertExactJson([
        'code' => -1_021,
        'msg' => 'Timestamp for this request is outside of the recvWindow.',
    ]);
});

it('outage wins over rate limit when both are on', function (): void {
    (new ToggleScenarioSwitch)(ScenarioSwitch::Outage, true);
    (new ToggleScenarioSwitch)(ScenarioSwitch::RateLimit, true);

    $this->getJson('/api/v3/ticker/bookTicker?symbol=USDCBRL')->assertServerError();
});

it('turning a switch back off restores the happy path', function (): void {
    (new ToggleScenarioSwitch)(ScenarioSwitch::Outage, true);
    (new ToggleScenarioSwitch)(ScenarioSwitch::Outage, false);

    $this->getJson('/api/v3/ticker/bookTicker?symbol=USDCBRL')->assertOk();
});
