<?php

declare(strict_types=1);

return [
    'scenario_switches' => [
        'title' => 'Switches de cenário (PIX)',
        'toggle_notification' => ':switch agora está :state',
        'state_on' => 'LIGADO',
        'state_off' => 'DESLIGADO',
    ],

    'armed_scenarios' => [
        'title' => 'Cenários armados (PIX)',
        'reason' => 'Motivo da recusa',
        'reason_helper' => 'Texto que viaja no log do webhook do desfecho. Vazio usa o motivo padrão da perna.',
        'extra_seconds' => 'Segundos extras',
        'extra_seconds_helper' => 'Somados ao relógio do avanço lazy antes de a cobrança virar paga. Vazio usa :default.',
        'armed_notification' => 'Armado: :outcome — vale para o próximo pedido',
        'rearmed_notification' => 'Re-armado: :outcome — o próximo pedido usa o parâmetro novo',
        'disarmed_notification' => 'Desarmado: :outcome',
        'arm_next' => 'Armar próximo',
        'arm_next_description' => 'Decide o desfecho do PRÓXIMO pedido desta perna. Sem armar nada, o happy path segue igual.',
        'arm_next_notification' => 'Perna armada',
        'outcome_field' => 'Desfecho',
        'disarm_option' => 'Nada armado (happy path)',
    ],

    'invoices' => [
        'columns' => [
            'tax_id' => 'CPF/CNPJ',
            'destined_status' => 'Destino armado',
            'extra_advance_seconds' => 'Atraso extra (s)',
            'frozen' => 'Congelada',
        ],
        'actions' => [
            'force_status' => 'Forçar status',
            'force_status_notification' => 'Status forçado',
            'status_field' => 'Status',
            'freeze' => 'Congelar',
            'unfreeze' => 'Descongelar',
            'frozen_notification' => 'Congelamento atualizado',
        ],
    ],

    'transfers' => [
        'columns' => [
            'tax_id' => 'CPF/CNPJ',
            'external_id' => 'External id',
            'destined_status' => 'Destino armado',
            'failure_reason' => 'Motivo da recusa',
            'held' => 'Retida',
        ],
        'actions' => [
            'force_status' => 'Forçar status',
            'force_status_notification' => 'Status forçado',
            'status_field' => 'Status',
        ],
    ],

    'brcode_payments' => [
        'columns' => [
            'tax_id' => 'CPF/CNPJ do recebedor',
            'destined_status' => 'Destino armado',
            'failure_reason' => 'Motivo da recusa',
            'held' => 'Retido',
        ],
        'actions' => [
            'force_status' => 'Forçar status',
            'force_status_notification' => 'Status forçado',
            'status_field' => 'Status',
        ],
    ],

    'dict_entries' => [
        'columns' => [
            'pix_key' => 'Chave PIX',
            'tax_id' => 'CPF/CNPJ',
            'bank_name' => 'Banco',
            'ispb' => 'ISPB',
            'account_type' => 'Tipo de conta',
        ],
        'actions' => [
            'register' => 'Registrar chave',
            'register_notification' => 'Chave PIX registrada',
        ],
    ],

    'webhook_emissions' => [
        'columns' => [
            'event_id' => 'Event id',
            'entity_id' => 'Entity id',
            'response_code' => 'HTTP',
            'sent_at' => 'Entregue em',
            'held' => 'Represada',
            'failed_reason' => 'Falha',
        ],
        'actions' => [
            'replay' => 'Reenviar',
            'replay_description' => 'Reenvia os bytes gravados com o mesmo event.id — exercita a idempotência do consumidor.',
            'replay_notification' => 'Emissão reenviada',
            'emit_corrupted' => 'Emitir corrompida',
            'emit_corrupted_description' => 'Emite o mesmo par subscription/evento assinado com outra chave — o consumidor deve recusar com 401.',
            'emit_corrupted_notification' => 'Emissão corrompida disparada',
            'release_hold' => 'Liberar represada',
            'release_hold_description' => 'Entrega agora a emissão que o cenário HoldNext segurou.',
            'release_hold_notification' => 'Emissão liberada',
        ],
    ],
];
