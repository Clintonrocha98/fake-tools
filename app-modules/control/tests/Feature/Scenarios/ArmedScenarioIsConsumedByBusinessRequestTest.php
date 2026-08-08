<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Invoice\Enums\InvoiceStatus;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Scenarios\Enums\InvoiceOutcome;
use He4rt\FakeStarkbank\Scenarios\Enums\PixLeg;
use He4rt\FakeStarkbank\Tests\Support\SignsRequests;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\NullWebhookEmitter;
use Illuminate\Support\Facades\Route;

uses(SignsRequests::class);

/*
|--------------------------------------------------------------------------
| O cenário armado por HTTP é o MESMO que o painel arma
|--------------------------------------------------------------------------
|
| A prova de que o `/control` não é uma segunda implementação: o pedido de
| negócio consome o cenário sem saber por onde ele foi armado, e o consumo
| continua sendo único.
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankClient();
    $this->app->bind(EmitsWebhookEvents::class, NullWebhookEmitter::class);
});

it('faz o próximo pedido de negócio consumir o cenário armado por HTTP', function (): void {
    $this->postJson('/control/starkbank/scenarios', [
        'leg' => PixLeg::StarkbankInvoice->value,
        'outcome' => InvoiceOutcome::Expire->value,
    ])->assertCreated();

    $this->postSigned('/v2/invoice', ['invoices' => [[
        'amount' => 1_000,
        'name' => 'Ada Lovelace',
        'taxId' => '012.345.678-90',
    ]]])->assertSuccessful();

    expect(Invoice::query()->sole()->destined_status)->toBe(InvoiceStatus::Expired);
});

it('gasta o cenário uma única vez: o segundo pedido volta ao happy path', function (): void {
    $this->postJson('/control/starkbank/scenarios', [
        'leg' => PixLeg::StarkbankInvoice->value,
        'outcome' => InvoiceOutcome::Expire->value,
    ])->assertCreated();

    $body = ['invoices' => [[
        'amount' => 1_000,
        'name' => 'Ada Lovelace',
        'taxId' => '012.345.678-90',
    ]]];

    $this->postSigned('/v2/invoice', $body)->assertSuccessful();
    $this->postSigned('/v2/invoice', $body)->assertSuccessful();

    // A ordem de criação não é recuperável pelo id (é o id numérico do
    // StarkBank, aleatório) nem por `created_at` (as duas nascem no mesmo
    // segundo): o que importa é que só UMA carrega o destino do cenário.
    $destinos = Invoice::query()->pluck('destined_status')->all();

    expect($destinos)->toHaveCount(2)
        ->and(array_filter($destinos, static fn (?InvoiceStatus $destino): bool => $destino === InvoiceStatus::Expired))->toHaveCount(1)
        ->and(array_filter($destinos, static fn (?InvoiceStatus $destino): bool => !$destino instanceof InvoiceStatus))->toHaveCount(1);
});

it('deixa as rotas do plano de controle fora dos middlewares de fake', function (): void {
    $proibidos = [
        'fake-starkbank.request-log', 'fake-starkbank.signed', 'fake-starkbank.scenario-switches',
        'fake-binance.request-log', 'fake-binance.signed', 'fake-binance.scenario-switches',
    ];

    $rotas = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($rota): bool => str_starts_with((string) $rota->uri(), 'control'));

    expect($rotas)->not->toBeEmpty();

    foreach ($rotas as $rota) {
        expect(array_intersect($proibidos, $rota->gatherMiddleware()))
            ->toBe([], 'A rota '.$rota->uri().' herdou middleware de fake.');
    }
});

it('não exige assinatura nas rotas do plano de controle', function (): void {
    // Nos fakes a verificação de assinatura É o produto; aqui seria atrito sem
    // fidelidade a ganhar.
    $this->getJson('/control/starkbank/scenarios')->assertOk();
});
