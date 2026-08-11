<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Kill-switch do plano de controle
    |--------------------------------------------------------------------------
    |
    | Desligado, o grupo `/control` nem se registra — 404, não 403. Um grupo
    | registrado e barrado por middleware continua aparecendo em `route:list` e
    | continua respondendo alguma coisa; só não registrar entrega o 404.
    |
    */

    'enabled' => env('FAKE_TOOLS_CONTROL_ENABLED', default: true),

    /*
    |--------------------------------------------------------------------------
    | Feed
    |--------------------------------------------------------------------------
    |
    | `limit` sem teto deixa um poll ingênuo puxar a tabela inteira. `retention`
    | é telemetria de dev, não auditoria: prune por idade, e o volume de um dia
    | cabe em qualquer lugar.
    |
    */

    // O cast é obrigatório, não cosmético: uma env definida no docker-compose
    // chega SEMPRE como string, e os consumidores leem com `Config::integer()`,
    // que recusa string e derruba a rota com 500.
    'feed' => [
        'default_limit' => (int) env('FAKE_TOOLS_CONTROL_FEED_DEFAULT_LIMIT', 100),
        'max_limit' => (int) env('FAKE_TOOLS_CONTROL_FEED_MAX_LIMIT', 500),
        'retention_hours' => (int) env('FAKE_TOOLS_CONTROL_FEED_RETENTION_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Snapshot
    |--------------------------------------------------------------------------
    |
    | Quantos registros recentes cada tipo traz em `GET /control/state`. O
    | retrato é para caber numa tela; o que precisar de paginação pertence a uma
    | rota própria.
    |
    */

    'state' => [
        'recent_limit' => (int) env('FAKE_TOOLS_CONTROL_STATE_RECENT_LIMIT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Conexão dedicada do feed
    |--------------------------------------------------------------------------
    |
    | O handler escreve fora da transação de negócio ({@see \He4rt\Control\Feed\ControlEventHandler}).
    | Em teste isto aponta para a conexão default, senão o rollback do teste não
    | desfaria o que a segunda conexão escreveu.
    |
    */

    'connection' => env('FAKE_TOOLS_CONTROL_CONNECTION', 'control'),

    /*
    |--------------------------------------------------------------------------
    | Reset de baseline
    |--------------------------------------------------------------------------
    |
    | Truncar tabelas merece cinto além do kill-switch: só nos ambientes desta
    | lista o reset roda.
    |
    */

    'reset' => [
        'allowed_environments' => ['local', 'development', 'dev', 'testing'],
    ],

];
