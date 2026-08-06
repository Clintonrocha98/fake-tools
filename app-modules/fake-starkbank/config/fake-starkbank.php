<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cliente (consumidor → fake)
    |--------------------------------------------------------------------------
    |
    | O Access-Id que o fake aceita nos headers Access-Id/Access-Time/
    | Access-Signature e a chave pública ECDSA secp256k1 (PEM) que verifica a
    | Access-Signature sobre `accessId:accessTime:body`. Par distinto do de
    | webhook — nunca compartilhado (ADR-0001). O PEM entra inline ou por
    | arquivo (preferido). O default do Access-Id espelha o canônico dos
    | fixtures do consumidor.
    |
    */

    'client' => [
        'access_id' => env('FAKE_STARKBANK_CLIENT_ACCESS_ID', 'project/6341320293482496'),
        'public_key' => env('FAKE_STARKBANK_CLIENT_PUBLIC_KEY'),
        'public_key_path' => env('FAKE_STARKBANK_CLIENT_PUBLIC_KEY_PATH'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook (fake → consumidor)
    |--------------------------------------------------------------------------
    |
    | Para onde o fake POSTa eventos — o POST /webhooks/starkbank do consumidor
    | — e a chave privada que assina o header Digital-Signature sobre o raw
    | body. As chaves de dev e o contrato de env chegam em ticket próprio;
    | aqui só a estrutura que vai recebê-las.
    |
    */

    'webhook' => [
        'url' => env('FAKE_STARKBANK_WEBHOOK_URL'),
        'private_key' => env('FAKE_STARKBANK_WEBHOOK_PRIVATE_KEY'),
        'private_key_path' => env('FAKE_STARKBANK_WEBHOOK_PRIVATE_KEY_PATH'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Workspace
    |--------------------------------------------------------------------------
    |
    | O workspace único que GET /v2/workspace serve (endpoint em ticket
    | próprio). Defaults espelham verbatim o fixture workspace_list_page1.json
    | do consumidor; `created` sem env cai no boot do processo.
    |
    */

    'workspace' => [
        'id' => env('FAKE_STARKBANK_WORKSPACE_ID', '6341320293482496'),
        'username' => env('FAKE_STARKBANK_WORKSPACE_USERNAME', 'brd-treasury'),
        'name' => env('FAKE_STARKBANK_WORKSPACE_NAME', 'BRD Treasury'),
        'allowed_tax_ids' => explode(',', (string) env('FAKE_STARKBANK_WORKSPACE_ALLOWED_TAX_IDS', '20.018.183/0001-80')),
        'status' => env('FAKE_STARKBANK_WORKSPACE_STATUS', 'active'),
        'organization_id' => env('FAKE_STARKBANK_WORKSPACE_ORGANIZATION_ID', '5716662276096000'),
        'picture_url' => env('FAKE_STARKBANK_WORKSPACE_PICTURE_URL', 'https://s3.amazonaws.com/starkbank/workspaces/brd-treasury.png'),
        'created' => env('FAKE_STARKBANK_WORKSPACE_CREATED', now()->toIso8601String()),
    ],

];
