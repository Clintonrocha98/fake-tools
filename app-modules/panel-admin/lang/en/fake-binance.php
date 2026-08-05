<?php

declare(strict_types=1);

return [
    'fiat_orders' => [
        'columns' => [
            'order_no' => 'Order no',
            'status_lazy' => 'Status (lazy)',
            'override' => 'Override',
            'unknown_wire' => 'Unknown wire',
            'frozen' => 'Frozen',
            'brcode_delay' => 'Brcode delay',
            'brcode_delay_description' => 'reads: :count',
        ],
        'actions' => [
            'credit_now' => 'Credit now',
            'credit_now_description' => 'Skips the lazy-advance clock and credits the ledger immediately.',
            'credit_now_notification' => 'Order credited',
            'force_status' => 'Fail with status',
            'force_status_notification' => 'Status forced',
            'status_field' => 'Status',
            'emit_unknown' => 'Emit unknown vocabulary',
            'emit_unknown_notification' => 'Unknown vocabulary emitted',
            'wire_status_field' => 'Arbitrary wire status',
            'delay_brcode' => 'Delay brcode',
            'delay_brcode_notification' => 'Brcode delay updated',
            'reads_field' => 'Reads without brcode',
            'reads_helper' => 'Empty removes the delay — brcode shows up again immediately.',
            'freeze' => 'Freeze',
            'unfreeze' => 'Unfreeze',
            'frozen_notification' => 'Freeze status updated',
        ],
    ],

    'spot_orders' => [
        'columns' => [
            'client_order_id' => 'Client order id',
            'unknown_wire' => 'Unknown wire',
            'executed_qty' => 'Executed qty',
            'quote_qty' => 'Quote qty',
        ],
        'actions' => [
            'reject' => 'Reject (REJECTED)',
            'reject_description' => 'Zeroes the fill and moves the order to REJECTED — no execution at all.',
            'reject_notification' => 'Order rejected',
            'expire_partially' => 'Fill partially + EXPIRED',
            'expire_partially_description' => 'Halves the executed fill and moves the order to EXPIRED.',
            'expire_partially_notification' => 'Order partially filled and expired',
            'emit_unknown' => 'Emit unknown vocabulary',
            'emit_unknown_notification' => 'Unknown vocabulary emitted',
            'raw_status_field' => 'Arbitrary status',
        ],
    ],

    'withdrawals' => [
        'columns' => [
            'withdraw_order_id' => 'Withdraw order id',
            'unknown_code' => 'Unknown code',
            'tx_id' => 'Tx id',
            'frozen' => 'Frozen',
        ],
        'actions' => [
            'complete_now' => 'Complete now',
            'complete_now_description' => 'Skips the lazy-advance clock and completes the withdraw immediately.',
            'complete_now_notification' => 'Withdraw completed',
            'force_status' => 'Fail with status',
            'force_status_notification' => 'Status forced',
            'status_field' => 'Status',
            'info_field' => 'Reason (info)',
            'emit_unknown' => 'Emit unknown vocabulary',
            'emit_unknown_notification' => 'Unknown vocabulary emitted',
            'raw_status_field' => 'Arbitrary status code',
            'freeze' => 'Freeze',
            'unfreeze' => 'Unfreeze',
            'frozen_notification' => 'Freeze status updated',
        ],
    ],

    'ledger_accounts' => [
        'actions' => [
            'new_balance' => 'New balance',
            'new_balance_notification' => 'Balance set',
            'edit_balance' => 'Edit balance',
            'edit_balance_notification' => 'Balance updated',
            'asset_field' => 'Asset',
        ],
    ],

    'scenario_switches' => [
        'title' => 'Scenario Switches',
        'turn_on' => 'Turn on the :switch switch?',
        'turn_off' => 'Turn off the :switch switch?',
        'toggle_notification' => ':switch is now :state',
        'state_on' => 'ON',
        'state_off' => 'OFF',
    ],
];
