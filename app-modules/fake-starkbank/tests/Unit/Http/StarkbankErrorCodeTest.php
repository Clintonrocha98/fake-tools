<?php

declare(strict_types=1);

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use He4rt\FakeStarkbank\Http\Errors\ErrorResponseFactory;
use He4rt\FakeStarkbank\Http\Errors\StarkbankErrorCode;

/*
 * O vocabulário de erro do módulo: o `code` que viaja na wire, o HTTP de cada
 * caminho de rejeição e os contratos Filament que o painel vai pendurar neles.
 */

it('fixa o code de wire e o HTTP de cada caminho de rejeição', function (StarkbankErrorCode $code, string $wire, int $status): void {
    expect($code->value)->toBe($wire)
        ->and($code->httpStatus())->toBe($status);
})->with([
    'Access-Id desconhecido' => [StarkbankErrorCode::InvalidAccessId, 'invalidAccessId', 401],
    'assinatura inválida' => [StarkbankErrorCode::InvalidSignature, 'invalidSignature', 401],
    'Access-Time fora da janela' => [StarkbankErrorCode::ExpiredAccessTime, 'expiredAccessTime', 401],
    'header de assinatura ausente' => [StarkbankErrorCode::InvalidRequest, 'invalidRequest', 400],
    'id desconhecido' => [StarkbankErrorCode::InvalidId, 'invalidId', 404],
    'chave PIX fora do DICT' => [StarkbankErrorCode::InvalidDictKey, 'invalidDictKey', 404],
]);

it('implementa os contratos Filament em todos os cases, sem buraco', function (): void {
    foreach (StarkbankErrorCode::cases() as $code) {
        expect($code)->toBeInstanceOf(HasLabel::class)
            ->and($code)->toBeInstanceOf(HasColor::class)
            ->and($code)->toBeInstanceOf(HasDescription::class)
            ->and($code->getLabel())->not->toBeEmpty()
            ->and($code->getColor())->not->toBeEmpty()
            ->and($code->getDescription())->not->toBeEmpty()
            ->and($code->defaultMessage())->not->toBeEmpty();
    }
});

it('dá uma cor própria a cada case, porque os códigos são causas distintas e não uma escala', function (): void {
    // O contrato HasColor aceita string semântica ou array da paleta; a
    // comparação é sobre o valor serializado para que os dois convivam.
    $cores = array_map(
        static fn (StarkbankErrorCode $code): string => json_encode($code->getColor(), JSON_THROW_ON_ERROR),
        StarkbankErrorCode::cases(),
    );

    expect($cores)->toHaveSameSize(array_unique($cores));
});

it('serializa qualquer código no envelope errors do StarkBank', function (StarkbankErrorCode $code): void {
    $response = new ErrorResponseFactory()->make($code);

    expect($response->getStatusCode())->toBe($code->httpStatus())
        ->and($response->getData(assoc: true))->toBe([
            'errors' => [
                ['code' => $code->value, 'message' => $code->defaultMessage()],
            ],
        ]);
})->with(StarkbankErrorCode::cases());

it('aceita uma mensagem sob medida sem trocar o código', function (): void {
    $response = new ErrorResponseFactory()->make(StarkbankErrorCode::InvalidRequest, 'Access-Time header is missing');

    expect($response->getData(assoc: true))->toBe([
        'errors' => [
            ['code' => 'invalidRequest', 'message' => 'Access-Time header is missing'],
        ],
    ]);
});
