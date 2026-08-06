<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Tests\Support\SignsRequests;

uses(SignsRequests::class);

/*
|--------------------------------------------------------------------------
| GET /v2/workspace
|--------------------------------------------------------------------------
|
| O primeiro endpoint autenticado do fake: prova o middleware ponta a ponta na
| rota real e serve o workspace de config no envelope {"workspaces": [...],
| "cursor": null}. Sem estado em banco — reconfigurar vale na chamada seguinte.
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankClient();

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
});

it('serve o workspace de config no vocabulário camelCase da wire', function (): void {
    $this->getSigned('/v2/workspace', $this->signedHeaders())
        ->assertOk()
        ->assertExactJson([
            'workspaces' => [
                [
                    'id' => '6341320293482496',
                    'username' => 'brd-treasury',
                    'name' => 'BRD Treasury',
                    'allowedTaxIds' => ['20.018.183/0001-80'],
                    'status' => 'active',
                    'organizationId' => '5716662276096000',
                    'pictureUrl' => 'https://s3.amazonaws.com/starkbank/workspaces/brd-treasury.png',
                    'created' => '2026-07-01T09:00:00.000000+00:00',
                ],
            ],
            'cursor' => null,
        ]);
});

it('encerra a paginação de cara com cursor null', function (): void {
    // O consumidor itera até `cursor === null`; uma página única que já encerra
    // é o suficiente, e é por isso que o fake nunca constrói a segunda página.
    $this->getSigned('/v2/workspace', $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('cursor', null)
        ->assertJsonCount(1, 'workspaces');
});

it('ignora o cursor recebido e devolve a mesma página única', function (): void {
    $this->getSigned('/v2/workspace?cursor='.rawurlencode('eyJwYWdlIjoyfQ=='), $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('cursor', null)
        ->assertJsonPath('workspaces.0.id', '6341320293482496');
});

it('serve pictureUrl null quando a config não aponta para uma imagem', function (): void {
    config(['fake-starkbank.workspace.picture_url' => null]);

    $this->getSigned('/v2/workspace', $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('workspaces.0.pictureUrl', null);
});

it('serve um status diferente de active quando a config arma esse cenário', function (): void {
    config(['fake-starkbank.workspace.status' => 'blocked']);

    $this->getSigned('/v2/workspace', $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('workspaces.0.status', 'blocked');
});

it('serve allowedTaxIds vazio quando a config arma esse cenário', function (): void {
    config(['fake-starkbank.workspace.allowed_tax_ids' => []]);

    $this->getSigned('/v2/workspace', $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('workspaces.0.allowedTaxIds', []);
});

it('aceita allowed_tax_ids como lista separada por vírgula, a forma que vem do env', function (): void {
    config(['fake-starkbank.workspace.allowed_tax_ids' => '20.018.183/0001-80, 20.018.183/0002-61']);

    $this->getSigned('/v2/workspace', $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('workspaces.0.allowedTaxIds', ['20.018.183/0001-80', '20.018.183/0002-61']);
});

it('normaliza created para o ISO-8601 com microssegundos dos fixtures', function (): void {
    config(['fake-starkbank.workspace.created' => '2026-07-02 09:00:00']);

    $this->getSigned('/v2/workspace', $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('workspaces.0.created', '2026-07-02T09:00:00.000000+00:00');
});

it('recusa um request sem os headers de assinatura com 400 invalidRequest', function (): void {
    $this->getSigned('/v2/workspace')
        ->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'invalidRequest');
});

it('recusa um Access-Id desconhecido com 401 invalidAccessId', function (): void {
    $this->getSigned('/v2/workspace', $this->signedHeaders(accessId: 'project/0000000000000000'))
        ->assertStatus(401)
        ->assertJsonPath('errors.0.code', 'invalidAccessId');
});

it('recusa um Access-Time fora da janela com 401 expiredAccessTime', function (): void {
    $this->getSigned('/v2/workspace', $this->signedHeaders(accessTime: now()->getTimestamp() - 3_600))
        ->assertStatus(401)
        ->assertJsonPath('errors.0.code', 'expiredAccessTime');
});

it('recusa uma assinatura de outra chave com 401 invalidSignature', function (): void {
    $this->getSigned('/v2/workspace', $this->signedHeaders(privateKey: $this->generateKeypair()))
        ->assertStatus(401)
        ->assertJsonPath('errors.0.code', 'invalidSignature');
});
