<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Avanço automático (lazy)
    |--------------------------------------------------------------------------
    |
    | Segundos entre a criação da invoice e o pagamento simulado
    | (created → paid). O avanço é LAZY: só acontece quando a invoice é LIDA
    | (GET single ou listagem), nunca por scheduler — a idade desde `created`
    | contra este valor é o que decide. Zero ou negativo desliga o pagamento
    | automático (a invoice fica em `created` até um cenário forçá-la), mas
    | nunca desliga o relógio de vencimento: `due` + graça continua levando a
    | overdue/expired, porque esse prazo é do próprio documento.
    |
    */

    'advance_seconds' => (int) env('FAKE_STARKBANK_INVOICE_ADVANCE_SECONDS', 60),

];
