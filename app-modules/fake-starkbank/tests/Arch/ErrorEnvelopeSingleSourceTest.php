<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| Fonte única do envelope de erro
|--------------------------------------------------------------------------
|
| "Toda rejeição sai pela ErrorResponseFactory" só é verdade enquanto ninguém
| escreve um `abort()` ou monta um `{"errors": [...]}` à mão em outro arquivo —
| e o consumidor, que lê `errors.0.code`, cai no fallback "HTTP {status}" quando
| isso acontece. Varre o src/ do módulo em vez de confiar na prosa do docblock.
|
*/

test('só a ErrorResponseFactory monta o envelope de erro do módulo', function (): void {
    $fonteUnica = 'Http/Errors/ErrorResponseFactory.php';
    $src = dirname(__DIR__, 2).'/src';

    $infratores = [];

    foreach (Finder::create()->files()->name('*.php')->in($src) as $arquivo) {
        $caminho = str_replace('\\', '/', $arquivo->getRelativePathname());

        if ($caminho === $fonteUnica) {
            continue;
        }

        // A varredura é sobre TOKENS, não sobre o texto do arquivo: os
        // docblocks do módulo citam o envelope de propósito, e um grep cru os
        // acusaria a cada vez que alguém documenta o formato corretamente.
        $tokens = token_get_all($arquivo->getContents());

        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING && mb_trim($token[1], '\'"') === 'errors') {
                $infratores[] = $caminho.' monta a key `errors` à mão';
            }

            if (is_array($token) && $token[0] === T_STRING && in_array($token[1], ['abort', 'abort_if', 'abort_unless'], strict: true)) {
                $infratores[] = $caminho.' rejeita por abort(), que renderiza HTML';
            }
        }
    }

    expect($infratores)->toBeEmpty();
});

test('toda rota do fake-starkbank exige assinatura', function (): void {
    // O único endpoint público previsto do contrato é o webhook do lado do
    // consumidor, que não vive aqui: qualquer rota deste módulo sem
    // `fake-starkbank.signed` é um endpoint aberto por esquecimento.
    $rotas = collect(Route::getRoutes())
        ->filter(fn ($rota): bool => str_starts_with((string) $rota->getActionName(), 'He4rt\\FakeStarkbank\\'));

    expect($rotas)->not->toBeEmpty();

    foreach ($rotas as $rota) {
        expect('fake-starkbank.signed')->toBeIn($rota->gatherMiddleware());
    }
});
