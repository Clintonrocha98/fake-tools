<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use He4rt\FakeStarkbank\Invoice\Actions\ListInvoices;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Tests\Support\SignsRequests;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\NullWebhookEmitter;

uses(SignsRequests::class);

/*
|--------------------------------------------------------------------------
| GET /v2/invoice?status=paid&after={cursor}
|--------------------------------------------------------------------------
|
| O extrato que `starkbank:poll-extrato` varre. O cursor viaja em `after`, o
| filtro de status é real e o avanço lazy roda ANTES do filtro.
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankClient();
    $this->app->bind(EmitsWebhookEvents::class, NullWebhookEmitter::class);
    config(['fake-starkbank-invoice.advance_seconds' => 60]);
});

it('filtra o extrato por status de verdade', function (): void {
    $paga = Invoice::factory()->paid()->create(['due' => now()->addDay()]);
    Invoice::factory()->create(['due' => now()->addDay()]);

    $response = $this->getSigned('/v2/invoice?status=paid', $this->signedHeaders());

    $response->assertOk()
        ->assertJsonCount(1, 'invoices')
        ->assertJsonPath('invoices.0.id', $paga->id)
        ->assertJsonPath('cursor', null);
});

it('serve cada item no mesmo shape magro da releitura singular', function (): void {
    Invoice::factory()->paid()->create(['due' => now()->addDay()]);

    $item = (array) $this->getSigned('/v2/invoice?status=paid', $this->signedHeaders())->assertOk()->json('invoices.0');

    expect(array_keys($item))->toEqualCanonicalizing([
        'id', 'amount', 'name', 'taxId', 'status', 'brcode', 'tags', 'created', 'due', 'expiration', 'updated',
    ]);
});

it('aplica o avanço lazy antes do filtro, então a invoice madurada nesta varredura já sai em status=paid', function (): void {
    // É este o fecho do ciclo emitir → avançar → varrer sem scheduler: filtrar
    // `status=paid` no SQL esconderia justamente a invoice que amadureceu agora.
    $invoice = Invoice::factory()->create(['due' => now()->addDay()]);

    $this->getSigned('/v2/invoice?status=paid', $this->signedHeaders())
        ->assertOk()
        ->assertJsonCount(0, 'invoices');

    $this->travel(61)->seconds();

    $this->getSigned('/v2/invoice?status=paid', $this->signedHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'invoices')
        ->assertJsonPath('invoices.0.id', $invoice->id);
});

it('sem filtro, lista todo o extrato', function (): void {
    Invoice::factory()->count(3)->create(['due' => now()->addDay()]);

    $this->getSigned('/v2/invoice', $this->signedHeaders())
        ->assertOk()
        ->assertJsonCount(3, 'invoices');
});

it('pagina em 100 itens e encerra a segunda página com cursor null', function (): void {
    Invoice::factory()->count(ListInvoices::PAGE_SIZE + 5)->create(['due' => now()->addDay()]);

    $primeira = $this->getSigned('/v2/invoice', $this->signedHeaders())->assertOk();

    $primeira->assertJsonCount(ListInvoices::PAGE_SIZE, 'invoices');

    $cursor = $primeira->json('cursor');

    expect($cursor)->toBeString();

    $segunda = $this->getSigned('/v2/invoice?after='.urlencode((string) $cursor), $this->signedHeaders())->assertOk();

    $segunda->assertJsonCount(5, 'invoices')
        ->assertJsonPath('cursor', null);

    $idsPrimeira = array_column((array) $primeira->json('invoices'), 'id');
    $idsSegunda = array_column((array) $segunda->json('invoices'), 'id');

    // Nenhuma invoice servida duas vezes e nenhuma perdida entre as páginas.
    expect(array_intersect($idsPrimeira, $idsSegunda))->toBeEmpty()
        ->and(count($idsPrimeira) + count($idsSegunda))->toBe(ListInvoices::PAGE_SIZE + 5);
});

it('serve o extrato do mais novo para o mais antigo', function (): void {
    $antiga = Invoice::factory()->paid()->create(['created_at' => CarbonImmutable::now()->subDays(2), 'due' => now()->addDay()]);
    $meio = Invoice::factory()->paid()->create(['created_at' => CarbonImmutable::now()->subDay(), 'due' => now()->addDay()]);
    $recente = Invoice::factory()->paid()->create(['due' => now()->addDay()]);

    $ids = array_column((array) $this->getSigned('/v2/invoice?status=paid', $this->signedHeaders())->assertOk()->json('invoices'), 'id');

    expect($ids)->toBe([$recente->id, $meio->id, $antiga->id]);
});

it('mantém a invoice recém-paga na primeira página com o extrato acima do teto de 100', function (): void {
    // O `poll-extrato` do consumidor manda UM request e ignora o cursor: se a
    // página 1 fosse a das mais ANTIGAS, tudo que ele lê num banco de dev que
    // sobrevive a restart seria arquivo morto, e a invoice desta sessão nunca
    // reconciliaria (ADR-0002).
    Invoice::factory()->count(ListInvoices::PAGE_SIZE)->paid()->create([
        'created_at' => CarbonImmutable::now()->subDays(30),
        'due' => now()->addDay(),
    ]);

    $recente = Invoice::factory()->paid()->create(['due' => now()->addDay()]);

    $ids = array_column((array) $this->getSigned('/v2/invoice?status=paid', $this->signedHeaders())->assertOk()->json('invoices'), 'id');

    expect($ids)->toHaveCount(ListInvoices::PAGE_SIZE)
        ->and($ids[0])->toBe($recente->id);
});

it('aceita uma data ISO-8601 em after, que é o que --after de poll-extrato manda na primeira página', function (): void {
    $velha = Invoice::factory()->create(['created_at' => CarbonImmutable::now()->subDays(3), 'due' => now()->addDay()]);
    $nova = Invoice::factory()->create(['due' => now()->addDay()]);

    $response = $this->getSigned(
        '/v2/invoice?after='.urlencode(CarbonImmutable::now()->subDay()->toIso8601String()),
        $this->signedHeaders(),
    );

    $ids = array_column((array) $response->assertOk()->json('invoices'), 'id');

    expect($ids)->toContain($nova->id)
        ->and($ids)->not->toContain($velha->id);
});

it('ignora um after ilegível e serve a primeira página, em vez de fingir extrato vazio', function (): void {
    Invoice::factory()->count(2)->create(['due' => now()->addDay()]);

    $this->getSigned('/v2/invoice?after=nao-e-cursor', $this->signedHeaders())
        ->assertOk()
        ->assertJsonCount(2, 'invoices');
});

it('recusa um status fora do vocabulário no envelope de erro', function (): void {
    $this->getSigned('/v2/invoice?status=liquidada', $this->signedHeaders())
        ->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'invalidRequest')
        ->assertJsonPath('errors.0.message', 'Invalid invoice status: liquidada');
});

it('exige assinatura como toda rota do fake', function (): void {
    $this->getSigned('/v2/invoice?status=paid')
        ->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'invalidRequest');
});
