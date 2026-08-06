<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Http\Auth\EcdsaSignatureVerifier;
use He4rt\FakeStarkbank\Tests\Support\SignsRequests;

uses(SignsRequests::class);

/*
 * A verificação ECDSA propriamente dita, isolada do HTTP: par gerado em runtime,
 * nenhuma chave comitada. Toda entrada degenerada precisa falhar FECHADA — um
 * verificador que devolve true por engano faz o fake autenticar qualquer um.
 */

it('aceita uma assinatura produzida pela chave privada correspondente', function (): void {
    $privateKey = $this->generateKeypair();
    $message = 'project/1:1700000000:';

    $signature = base64_encode((string) $privateKey->withHash('sha256')->sign($message));

    expect(new EcdsaSignatureVerifier((string) $privateKey->getPublicKey())->verify($message, $signature))->toBeTrue();
});

it('recusa a mesma assinatura sobre outra mensagem', function (): void {
    $privateKey = $this->generateKeypair();

    $signature = base64_encode((string) $privateKey->withHash('sha256')->sign('project/1:1700000000:'));

    expect(new EcdsaSignatureVerifier((string) $privateKey->getPublicKey())->verify('project/1:1700000001:', $signature))->toBeFalse();
});

it('recusa uma assinatura de outra chave privada', function (): void {
    $privateKey = $this->generateKeypair();
    $outraChave = $this->generateKeypair();
    $message = 'project/1:1700000000:';

    $signature = base64_encode((string) $outraChave->withHash('sha256')->sign($message));

    expect(new EcdsaSignatureVerifier((string) $privateKey->getPublicKey())->verify($message, $signature))->toBeFalse();
});

it('falha fechada quando o fake não tem uma chave pública utilizável', function (string $pem): void {
    expect(new EcdsaSignatureVerifier($pem)->verify('project/1:1700000000:', 'cXVhbHF1ZXItY29pc2E='))->toBeFalse();
})->with([
    'PEM vazio' => [''],
    'PEM só com espaços' => ["   \n"],
    'PEM que não é uma chave' => ['isto não é um PEM'],
]);

it('falha fechada quando a assinatura não é um DER base64 utilizável', function (?string $signature): void {
    $privateKey = $this->generateKeypair();

    expect(new EcdsaSignatureVerifier((string) $privateKey->getPublicKey())->verify('project/1:1700000000:', $signature))->toBeFalse();
})->with([
    'assinatura nula' => [null],
    'assinatura vazia' => [''],
    'base64 corrompido' => ['não-é-base64!!'],
    'base64 válido que não é DER' => [base64_encode('nada disso é uma assinatura')],
]);
