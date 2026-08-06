<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use He4rt\FakeStarkbank\Tests\Contract\Support\AssertsRecordedShape;
use He4rt\FakeStarkbank\Tests\Support\SignsRequests;
use PHPUnit\Framework\ExpectationFailedException;

uses(SignsRequests::class, AssertsRecordedShape::class);

/*
 * O shape de GET /v2/workspace contra os dois fixtures gravados do consumidor.
 * Cada página é um SCHEMA comparado isoladamente — página 1 com `pictureUrl`
 * string, página 2 com `pictureUrl` null —, nunca uma contagem de itens: o fake
 * serve o workspace de config numa página única que já encerra em `cursor: null`.
 */

beforeEach(fn () => $this->configureFakeStarkbankClient());

it('responde no shape da página 1 do fixture do consumidor', function (): void {
    config([
        'fake-starkbank.workspace.id' => '6341320293482496',
        'fake-starkbank.workspace.username' => 'brd-treasury',
        'fake-starkbank.workspace.name' => 'BRD Treasury',
        'fake-starkbank.workspace.allowed_tax_ids' => ['20.018.183/0001-80'],
        'fake-starkbank.workspace.status' => 'active',
        'fake-starkbank.workspace.organization_id' => '5716662276096000',
        'fake-starkbank.workspace.picture_url' => 'https://s3.amazonaws.com/starkbank/workspaces/brd-treasury.png',
        'fake-starkbank.workspace.created' => '2026-07-01T09:00:00.000000+00:00',
    ]);

    $response = $this->getSigned('/v2/workspace', $this->signedHeaders());

    $response->assertOk();

    $fixture = $this->loadContractFixture('workspace/workspace_list_page1.json');

    // `cursor` do fixture é a string da próxima página; o fake sempre encerra em
    // null, e a comparação é de FORMA — a key precisa existir, o valor não é
    // comparado (nullable por contrato: é assim que o consumidor para de paginar).
    unset($fixture['cursor']);

    $this->assertMatchesRecordedShape($fixture, (array) $response->json());
    $response->assertJsonPath('cursor', null);
});

it('responde no shape da página 2 do fixture do consumidor, com pictureUrl e cursor nulos', function (): void {
    config([
        'fake-starkbank.workspace.id' => '5716662276096000',
        'fake-starkbank.workspace.username' => 'brd-reserve',
        'fake-starkbank.workspace.name' => 'BRD Reserve',
        'fake-starkbank.workspace.allowed_tax_ids' => ['20.018.183/0002-61'],
        'fake-starkbank.workspace.status' => 'active',
        'fake-starkbank.workspace.organization_id' => '5716662276096000',
        'fake-starkbank.workspace.picture_url' => null,
        'fake-starkbank.workspace.created' => '2026-07-02T09:00:00.000000+00:00',
    ]);

    $response = $this->getSigned('/v2/workspace', $this->signedHeaders());

    $response->assertOk();

    $this->assertMatchesRecordedShape(
        $this->loadContractFixture('workspace/workspace_list_page2.json'),
        (array) $response->json(),
    );
});

it('serve created num formato que o consumidor parseia como data', function (): void {
    // WorkspaceCollectionResponse materializa `created` num CarbonImmutable —
    // uma string que não parseia derruba o prefill de contas do consumidor.
    $response = $this->getSigned('/v2/workspace', $this->signedHeaders());

    $created = $response->assertOk()->json('workspaces.0.created');

    expect($created)->toBeString()
        ->and(CarbonImmutable::parse((string) $created)->toIso8601String())->toBeString();
});

it('carrega todas as keys camelCase que o consumidor lê, sem nenhuma em snake_case', function (): void {
    $workspace = (array) $this->getSigned('/v2/workspace', $this->signedHeaders())->assertOk()->json('workspaces.0');

    expect(array_keys($workspace))->toEqualCanonicalizing([
        'id',
        'username',
        'name',
        'allowedTaxIds',
        'status',
        'organizationId',
        'pictureUrl',
        'created',
    ]);
});

it('devolve o envelope de erro do StarkBank em toda rejeição, nunca HTML', function (): void {
    $response = $this->getSigned('/v2/workspace', $this->signedHeaders(privateKey: $this->generateKeypair()));

    $response->assertStatus(401)
        ->assertHeader('content-type', 'application/json');

    $this->assertMatchesRecordedShape(
        $this->loadContractFixture('errors/error_envelope.json'),
        (array) $response->json(),
    );

    $response->assertJsonPath('errors.0.code', 'invalidSignature');
});

it('quebra quando uma key documentada some da resposta', function (): void {
    // Prova do mecanismo: sem isto, a suíte de contrato é teatro.
    $fixture = $this->loadContractFixture('workspace/workspace_list_page1.json');

    $drifted = $fixture;
    unset($drifted['workspaces'][0]['allowedTaxIds']);

    expect(fn () => $this->assertMatchesRecordedShape($fixture, $drifted))
        ->toThrow(ExpectationFailedException::class);
});

it('quebra quando uma key documentada é renomeada para snake_case', function (): void {
    $fixture = $this->loadContractFixture('workspace/workspace_list_page1.json');

    $drifted = $fixture;
    $drifted['workspaces'][0]['allowed_tax_ids'] = $drifted['workspaces'][0]['allowedTaxIds'];
    unset($drifted['workspaces'][0]['allowedTaxIds']);

    expect(fn () => $this->assertMatchesRecordedShape($fixture, $drifted))
        ->toThrow(ExpectationFailedException::class);
});

it('quebra quando allowedTaxIds deixa de ser uma lista', function (): void {
    $fixture = $this->loadContractFixture('workspace/workspace_list_page1.json');

    $drifted = $fixture;
    $drifted['workspaces'][0]['allowedTaxIds'] = '20.018.183/0001-80';

    expect(fn () => $this->assertMatchesRecordedShape($fixture, $drifted))
        ->toThrow(ExpectationFailedException::class);
});

it('nunca quebra por uma key extra além do que o fixture documenta', function (): void {
    $fixture = $this->loadContractFixture('workspace/workspace_list_page1.json');

    $comExtra = $fixture;
    $comExtra['workspaces'][0]['pictureUrlSmall'] = 'https://example.test/small.png';

    $this->assertMatchesRecordedShape($fixture, $comExtra);

    expect(value: true)->toBeTrue(); // chegar até aqui sem exceção é a prova
});
