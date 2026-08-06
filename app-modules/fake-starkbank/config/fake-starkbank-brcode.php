<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Avanço automático (lazy)
    |--------------------------------------------------------------------------
    |
    | Segundos entre dois patamares do ciclo de vida do pagamento de BR Code:
    | 1× leva created → processing, 2× leva a success. O avanço é LAZY: só
    | acontece quando o pagamento é LIDO (GET single ou listagem), nunca por
    | scheduler — a idade desde a criação contra este valor é o que decide.
    | Zero ou negativo congela o pagamento em `created` até um cenário forçá-lo.
    |
    | `failed` nunca sai daqui: é desfecho de cenário, não do relógio.
    |
    */

    'advance_seconds' => (int) env('FAKE_STARKBANK_BRCODE_ADVANCE_SECONDS', 60),

    /*
    |--------------------------------------------------------------------------
    | Campos do preview que o fake não tem como calcular
    |--------------------------------------------------------------------------
    |
    | O `reconciliationId` e a `description` de um preview nascem, no provedor
    | real, do arranjo dinâmico que emitiu o BR Code. Um BR Code ESTÁTICO não
    | carrega nenhum dos dois, e o fake não tem arranjo — então serve a
    | constante do fixture, a mesma disciplina do fake-binance para conceito
    | que não existe deste lado. O consumidor lê a `description` só para exibir;
    | nada ramifica por ela.
    |
    */

    'preview' => [
        'reconciliation_id' => env('FAKE_STARKBANK_BRCODE_RECONCILIATION_ID', 'recon-9f2c'),
        'description' => env('FAKE_STARKBANK_BRCODE_DESCRIPTION', 'Invoice 2026-07'),
    ],

];
