<?php

declare(strict_types=1);

return [
    'fiat_orders' => [
        'columns' => [
            'order_no' => 'Número da ordem',
            'status_lazy' => 'Status (lazy)',
            'override' => 'Override',
            'unknown_wire' => 'Wire desconhecido',
            'frozen' => 'Congelada',
            'brcode_delay' => 'Atraso brcode',
            'brcode_delay_description' => 'lidas: :count',
        ],
        'actions' => [
            'credit_now' => 'Creditar agora',
            'credit_now_description' => 'Pula o relógio do avanço lazy e credita o ledger imediatamente.',
            'credit_now_notification' => 'Ordem creditada',
            'force_status' => 'Falhar com status',
            'force_status_notification' => 'Status forçado',
            'status_field' => 'Status',
            'emit_unknown' => 'Emitir vocabulário desconhecido',
            'emit_unknown_notification' => 'Vocabulário desconhecido emitido',
            'wire_status_field' => 'Status de wire arbitrário',
            'delay_brcode' => 'Atrasar brcode',
            'delay_brcode_notification' => 'Atraso de brcode atualizado',
            'reads_field' => 'Leituras sem brcode',
            'reads_helper' => 'Vazio remove o atraso — brcode volta a aparecer imediatamente.',
            'freeze' => 'Congelar',
            'unfreeze' => 'Descongelar',
            'frozen_notification' => 'Congelamento atualizado',
        ],
    ],

    'spot_orders' => [
        'columns' => [
            'client_order_id' => 'Client order id',
            'unknown_wire' => 'Wire desconhecido',
            'executed_qty' => 'Quantidade executada',
            'quote_qty' => 'Quantidade em quote',
        ],
        'actions' => [
            'reject' => 'Recusar (REJECTED)',
            'reject_description' => 'Zera o fill e move a ordem para REJECTED — sem execução alguma.',
            'reject_notification' => 'Ordem recusada',
            'expire_partially' => 'Preencher parcial + EXPIRED',
            'expire_partially_description' => 'Reduz o fill à metade do executado e move a ordem para EXPIRED.',
            'expire_partially_notification' => 'Ordem preenchida parcialmente e expirada',
            'emit_unknown' => 'Emitir vocabulário desconhecido',
            'emit_unknown_notification' => 'Vocabulário desconhecido emitido',
            'raw_status_field' => 'Status arbitrário',
        ],
    ],

    'withdrawals' => [
        'columns' => [
            'withdraw_order_id' => 'Withdraw order id',
            'unknown_code' => 'Código desconhecido',
            'tx_id' => 'Tx id',
            'frozen' => 'Congelado',
        ],
        'actions' => [
            'complete_now' => 'Completar agora',
            'complete_now_description' => 'Pula o relógio do avanço lazy e conclui o withdraw imediatamente.',
            'complete_now_notification' => 'Withdraw concluído',
            'force_status' => 'Falhar com status',
            'force_status_notification' => 'Status forçado',
            'status_field' => 'Status',
            'info_field' => 'Motivo (info)',
            'emit_unknown' => 'Emitir vocabulário desconhecido',
            'emit_unknown_notification' => 'Vocabulário desconhecido emitido',
            'raw_status_field' => 'Código de status arbitrário',
            'freeze' => 'Congelar',
            'unfreeze' => 'Descongelar',
            'frozen_notification' => 'Congelamento atualizado',
        ],
    ],

    'ledger_accounts' => [
        'actions' => [
            'new_balance' => 'Novo saldo',
            'new_balance_notification' => 'Saldo definido',
            'edit_balance' => 'Editar saldo',
            'edit_balance_notification' => 'Saldo atualizado',
            'asset_field' => 'Asset',
        ],
    ],

    'scenario_switches' => [
        'title' => 'Switches de cenário',
        'toggle_notification' => ':switch agora está :state',
        'state_on' => 'LIGADO',
        'state_off' => 'DESLIGADO',
    ],

    'armed_scenarios' => [
        'title' => 'Cenários armados',
        'fraction' => 'Fração do fill',
        'fraction_helper' => 'Quanto da ordem executa no desfecho parcial. Vazio usa metade.',
        'error_code' => 'Código de erro',
        'raw_status' => 'Status arbitrário',
        'armed_notification' => 'Armado: :outcome — vale para o próximo pedido',
        'disarmed_notification' => 'Desarmado: :outcome',
    ],
];
