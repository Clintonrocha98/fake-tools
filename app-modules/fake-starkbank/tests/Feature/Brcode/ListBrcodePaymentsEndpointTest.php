<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use He4rt\FakeStarkbank\Brcode\Actions\ListBrcodePayments;
use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;
use He4rt\FakeStarkbank\Tests\Support\SignsRequests;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\NullWebhookEmitter;

uses(SignsRequests::class);

/*
|--------------------------------------------------------------------------
| GET /v2/brcode-payment?status=success&after={cursor}
|--------------------------------------------------------------------------
|
| O extrato de funding que a rede de segurança da conciliação varre. O cursor
| viaja em `after`, o filtro de status é real e o avanço lazy roda ANTES do
| filtro.
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankClient();
    $this->app->bind(EmitsWebhookEvents::class, NullWebhookEmitter::class);
    config(['fake-starkbank-brcode.advance_seconds' => 60]);
});

it('filtra o extrato por status de verdade', function (): void {
    $liquidado = BrcodePayment::factory()->settled()->create();
    BrcodePayment::factory()->failed()->create();

    $this->getSigned('/v2/brcode-payment?status=success', $this->signedHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'payments')
        ->assertJsonPath('payments.0.id', $liquidado->id)
        ->assertJsonPath('cursor', null);
});

it('serve cada item no mesmo shape da releitura singular', function (): void {
    BrcodePayment::factory()->settled()->create();

    $item = (array) $this->getSigned('/v2/brcode-payment?status=success', $this->signedHeaders())->assertOk()->json('payments.0');

    expect(array_keys($item))->toEqualCanonicalizing([
        'id', 'brcode', 'taxId', 'amount', 'status', 'tags', 'created', 'updated',
    ]);
});

it('aplica o avanço lazy antes do filtro, então o funding liquidado nesta varredura já sai em status=success', function (): void {
    $pagamento = BrcodePayment::factory()->create();

    $this->getSigned('/v2/brcode-payment?status=success', $this->signedHeaders())
        ->assertOk()
        ->assertJsonCount(0, 'payments');

    $this->travel(121)->seconds();

    $this->getSigned('/v2/brcode-payment?status=success', $this->signedHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'payments')
        ->assertJsonPath('payments.0.id', $pagamento->id);
});

it('sem filtro, lista todo o extrato', function (): void {
    BrcodePayment::factory()->count(3)->settled()->create();

    $this->getSigned('/v2/brcode-payment', $this->signedHeaders())
        ->assertOk()
        ->assertJsonCount(3, 'payments');
});

it('serve o extrato do mais novo para o mais antigo', function (): void {
    // A página 1 precisa ser a janela recente: o `poll-extrato` manda um
    // request só e não segue o cursor (ADR-0002).
    $antigo = BrcodePayment::factory()->settled()->create(['created_at' => CarbonImmutable::now()->subDays(2)]);
    $recente = BrcodePayment::factory()->settled()->create();

    $ids = array_column((array) $this->getSigned('/v2/brcode-payment?status=success', $this->signedHeaders())->assertOk()->json('payments'), 'id');

    expect($ids)->toBe([$recente->id, $antigo->id]);
});

it('pagina em 100 itens e encerra a segunda página com cursor null', function (): void {
    BrcodePayment::factory()->count(ListBrcodePayments::PAGE_SIZE + 5)->settled()->create();

    $primeira = $this->getSigned('/v2/brcode-payment', $this->signedHeaders())->assertOk();

    $primeira->assertJsonCount(ListBrcodePayments::PAGE_SIZE, 'payments');

    $cursor = $primeira->json('cursor');

    expect($cursor)->toBeString();

    $segunda = $this->getSigned('/v2/brcode-payment?after='.urlencode((string) $cursor), $this->signedHeaders())->assertOk();

    $segunda->assertJsonCount(5, 'payments')
        ->assertJsonPath('cursor', null);

    $idsPrimeira = array_column((array) $primeira->json('payments'), 'id');
    $idsSegunda = array_column((array) $segunda->json('payments'), 'id');

    // Nenhum pagamento servido duas vezes e nenhum perdido entre as páginas.
    expect(array_intersect($idsPrimeira, $idsSegunda))->toBeEmpty()
        ->and(count($idsPrimeira) + count($idsSegunda))->toBe(ListBrcodePayments::PAGE_SIZE + 5);
});

it('aceita uma data ISO-8601 em after, que é o que a varredura manda na primeira página', function (): void {
    $velho = BrcodePayment::factory()->settled()->create(['created_at' => CarbonImmutable::now()->subDays(3)]);
    $novo = BrcodePayment::factory()->settled()->create();

    $response = $this->getSigned(
        '/v2/brcode-payment?after='.urlencode(CarbonImmutable::now()->subDay()->toIso8601String()),
        $this->signedHeaders(),
    );

    $ids = array_column((array) $response->assertOk()->json('payments'), 'id');

    expect($ids)->toContain($novo->id)
        ->and($ids)->not->toContain($velho->id);
});

it('ignora um after ilegível e serve a primeira página, em vez de fingir extrato vazio', function (): void {
    BrcodePayment::factory()->count(2)->settled()->create();

    $this->getSigned('/v2/brcode-payment?after=nao-e-cursor', $this->signedHeaders())
        ->assertOk()
        ->assertJsonCount(2, 'payments');
});

it('recusa um status fora do vocabulário no envelope de erro', function (): void {
    $this->getSigned('/v2/brcode-payment?status=liquidado', $this->signedHeaders())
        ->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'invalidRequest')
        ->assertJsonPath('errors.0.message', 'Invalid brcode payment status: liquidado');
});

it('exige assinatura como toda rota do fake', function (): void {
    $this->getSigned('/v2/brcode-payment?status=success')
        ->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'invalidRequest');
});
