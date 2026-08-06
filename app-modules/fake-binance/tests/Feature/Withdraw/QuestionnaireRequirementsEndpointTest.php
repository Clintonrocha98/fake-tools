<?php

declare(strict_types=1);

use He4rt\FakeBinance\Tests\Support\SignsRequests;

uses(SignsRequests::class);

beforeEach(function (): void {
    $this->configureFakeBinanceCredentials();
});

it('answers with no questionnaire requirement by default, letting the wallet delivery happy path through', function (): void {
    $response = $this->getJson(
        $this->signedUri('/sapi/v1/localentity/questionnaire-requirements', []),
        $this->apiKeyHeader(),
    );

    $response->assertOk()->assertExactJson(['questionnaireCountryCode' => null]);
});

it('answers with the configured country, making the consumer refuse the delivery before any withdraw', function (): void {
    config(['fake-binance.travel_rule_questionnaire_country' => 'BR']);

    $response = $this->getJson(
        $this->signedUri('/sapi/v1/localentity/questionnaire-requirements', []),
        $this->apiKeyHeader(),
    );

    $response->assertOk()->assertExactJson(['questionnaireCountryCode' => 'BR']);
});

it('serves NIL verbatim — the consumer reads it as "no requirement", same path as the default', function (): void {
    config(['fake-binance.travel_rule_questionnaire_country' => 'NIL']);

    $response = $this->getJson(
        $this->signedUri('/sapi/v1/localentity/questionnaire-requirements', []),
        $this->apiKeyHeader(),
    );

    $response->assertOk()->assertExactJson(['questionnaireCountryCode' => 'NIL']);
});

it('treats a blank configured value as no requirement', function (): void {
    config(['fake-binance.travel_rule_questionnaire_country' => '  ']);

    $response = $this->getJson(
        $this->signedUri('/sapi/v1/localentity/questionnaire-requirements', []),
        $this->apiKeyHeader(),
    );

    $response->assertOk()->assertExactJson(['questionnaireCountryCode' => null]);
});

it('refuses an unsigned request with the spot/wallet error envelope', function (): void {
    $response = $this->getJson('/sapi/v1/localentity/questionnaire-requirements');

    $response->assertStatus(401)->assertExactJson([
        'code' => -2_014,
        'msg' => 'API-key format invalid.',
    ]);
});

it('refuses a wrong signature with -1022 in the spot/wallet envelope', function (): void {
    $response = $this->getJson(
        '/sapi/v1/localentity/questionnaire-requirements?timestamp='.now()->getTimestampMs().'&signature=deadbeef',
        $this->apiKeyHeader(),
    );

    $response->assertStatus(400)->assertJson(['code' => -1_022]);
});
