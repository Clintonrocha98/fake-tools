<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Http\Auth\SignedRequestMessage;

/*
 * A composição da mensagem assinada é um contrato byte a byte com
 * `RequestSigner::stringToSign()` do consumidor: `accessId:accessTime:body`.
 * Os dois casos abaixo são os mesmos que o unit test de lá fixa.
 */

it('fixa a mensagem de um POST com body JSON', function (): void {
    expect(SignedRequestMessage::compose('project/6341320293482496', '1700000000', '{"invoices":[]}'))
        ->toBe('project/6341320293482496:1700000000:{"invoices":[]}');
});

it('fixa a mensagem de um GET, cujo body é vazio', function (): void {
    expect(SignedRequestMessage::compose('project/6341320293482496', '1700000000', ''))
        ->toBe('project/6341320293482496:1700000000:');
});

it('não carrega a query string, que é parte da URL e nunca da assinatura', function (): void {
    expect(SignedRequestMessage::compose('project/1', '1700000000', ''))
        ->not->toContain('cursor');
});
