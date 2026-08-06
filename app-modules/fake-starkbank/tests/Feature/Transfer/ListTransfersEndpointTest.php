<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use He4rt\FakeStarkbank\Tests\Support\SignsRequests;
use He4rt\FakeStarkbank\Transfer\Actions\ListTransfers;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\NullWebhookEmitter;

uses(SignsRequests::class);

/*
|--------------------------------------------------------------------------
| GET /v2/transfer?status=success&after={cursor}
|--------------------------------------------------------------------------
|
| O extrato de cash-out que `starkbank:poll-extrato` varre. O cursor viaja em
| `after`, o filtro de status é real e o avanço lazy roda ANTES do filtro.
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankClient();
    $this->app->bind(EmitsWebhookEvents::class, NullWebhookEmitter::class);
    config(['fake-starkbank-transfer.advance_seconds' => 60]);
});

it('filtra o extrato por status de verdade', function (): void {
    $liquidada = Transfer::factory()->settled()->create();
    Transfer::factory()->failed()->create();

    $this->getSigned('/v2/transfer?status=success', $this->signedHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'transfers')
        ->assertJsonPath('transfers.0.id', $liquidada->id)
        ->assertJsonPath('cursor', null);
});

it('serve cada item no mesmo shape da releitura singular', function (): void {
    Transfer::factory()->settled()->create();

    $item = (array) $this->getSigned('/v2/transfer?status=success', $this->signedHeaders())->assertOk()->json('transfers.0');

    expect(array_keys($item))->toEqualCanonicalizing([
        'id', 'amount', 'name', 'taxId', 'bankCode', 'accountType', 'status', 'tags', 'created', 'updated',
    ]);
});

it('aplica o avanço lazy antes do filtro, então a transfer liquidada nesta varredura já sai em status=success', function (): void {
    // É este o fecho do ciclo despachar → avançar → varrer sem scheduler.
    $transfer = Transfer::factory()->create();

    $this->getSigned('/v2/transfer?status=success', $this->signedHeaders())
        ->assertOk()
        ->assertJsonCount(0, 'transfers');

    $this->travel(121)->seconds();

    $this->getSigned('/v2/transfer?status=success', $this->signedHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'transfers')
        ->assertJsonPath('transfers.0.id', $transfer->id);
});

it('sem filtro, lista todo o extrato', function (): void {
    Transfer::factory()->count(3)->settled()->create();

    $this->getSigned('/v2/transfer', $this->signedHeaders())
        ->assertOk()
        ->assertJsonCount(3, 'transfers');
});

it('serve o extrato do mais novo para o mais antigo', function (): void {
    // A página 1 precisa ser a janela recente: o `poll-extrato` manda um
    // request só e não segue o cursor (ADR-0002).
    $antiga = Transfer::factory()->settled()->create(['created_at' => CarbonImmutable::now()->subDays(2)]);
    $recente = Transfer::factory()->settled()->create();

    $ids = array_column((array) $this->getSigned('/v2/transfer?status=success', $this->signedHeaders())->assertOk()->json('transfers'), 'id');

    expect($ids)->toBe([$recente->id, $antiga->id]);
});

it('pagina em 100 itens e encerra a segunda página com cursor null', function (): void {
    Transfer::factory()->count(ListTransfers::PAGE_SIZE + 5)->settled()->create();

    $primeira = $this->getSigned('/v2/transfer', $this->signedHeaders())->assertOk();

    $primeira->assertJsonCount(ListTransfers::PAGE_SIZE, 'transfers');

    $cursor = $primeira->json('cursor');

    expect($cursor)->toBeString();

    $segunda = $this->getSigned('/v2/transfer?after='.urlencode((string) $cursor), $this->signedHeaders())->assertOk();

    $segunda->assertJsonCount(5, 'transfers')
        ->assertJsonPath('cursor', null);

    $idsPrimeira = array_column((array) $primeira->json('transfers'), 'id');
    $idsSegunda = array_column((array) $segunda->json('transfers'), 'id');

    // Nenhuma transfer servida duas vezes e nenhuma perdida entre as páginas.
    expect(array_intersect($idsPrimeira, $idsSegunda))->toBeEmpty()
        ->and(count($idsPrimeira) + count($idsSegunda))->toBe(ListTransfers::PAGE_SIZE + 5);
});

it('aceita uma data ISO-8601 em after, que é o que --after de poll-extrato manda na primeira página', function (): void {
    $velha = Transfer::factory()->settled()->create(['created_at' => CarbonImmutable::now()->subDays(3)]);
    $nova = Transfer::factory()->settled()->create();

    $response = $this->getSigned(
        '/v2/transfer?after='.urlencode(CarbonImmutable::now()->subDay()->toIso8601String()),
        $this->signedHeaders(),
    );

    $ids = array_column((array) $response->assertOk()->json('transfers'), 'id');

    expect($ids)->toContain($nova->id)
        ->and($ids)->not->toContain($velha->id);
});

it('ignora um after ilegível e serve a primeira página, em vez de fingir extrato vazio', function (): void {
    Transfer::factory()->count(2)->settled()->create();

    $this->getSigned('/v2/transfer?after=nao-e-cursor', $this->signedHeaders())
        ->assertOk()
        ->assertJsonCount(2, 'transfers');
});

it('recusa um status fora do vocabulário no envelope de erro', function (): void {
    $this->getSigned('/v2/transfer?status=liquidada', $this->signedHeaders())
        ->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'invalidRequest')
        ->assertJsonPath('errors.0.message', 'Invalid transfer status: liquidada');
});

it('exige assinatura como toda rota do fake', function (): void {
    $this->getSigned('/v2/transfer?status=success')
        ->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'invalidRequest');
});
