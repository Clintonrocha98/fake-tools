<?php

declare(strict_types=1);

return [
    'scenario_switches' => [
        'title' => 'Scenario Switches (PIX)',
        'toggle_notification' => ':switch is now :state',
        'state_on' => 'ON',
        'state_off' => 'OFF',
    ],

    'armed_scenarios' => [
        'title' => 'Armed Scenarios (PIX)',
        'reason' => 'Refusal reason',
        'reason_helper' => "Text carried in the outcome's webhook log. Empty uses the leg's default reason.",
        'extra_seconds' => 'Extra seconds',
        'extra_seconds_helper' => 'Added to the lazy-advance clock before the invoice turns paid. Empty uses :default.',
        'armed_notification' => 'Armed: :outcome — good for the next request',
        'rearmed_notification' => 'Re-armed: :outcome — the next request uses the new parameter',
        'disarmed_notification' => 'Disarmed: :outcome',
        'arm_next' => 'Arm next',
        'arm_next_description' => 'Decides the outcome of the NEXT request on this leg. With nothing armed, the happy path is unchanged.',
        'arm_next_notification' => 'Leg armed',
        'outcome_field' => 'Outcome',
        'disarm_option' => 'Nothing armed (happy path)',
    ],

    'invoices' => [
        'columns' => [
            'tax_id' => 'Tax id',
            'destined_status' => 'Armed destination',
            'extra_advance_seconds' => 'Extra delay (s)',
            'frozen' => 'Frozen',
        ],
        'actions' => [
            'force_status' => 'Force status',
            'force_status_notification' => 'Status forced',
            'status_field' => 'Status',
            'freeze' => 'Freeze',
            'unfreeze' => 'Unfreeze',
            'frozen_notification' => 'Freeze updated',
        ],
    ],

    'transfers' => [
        'columns' => [
            'tax_id' => 'Tax id',
            'external_id' => 'External id',
            'destined_status' => 'Armed destination',
            'failure_reason' => 'Refusal reason',
            'held' => 'Held',
        ],
        'actions' => [
            'force_status' => 'Force status',
            'force_status_notification' => 'Status forced',
            'status_field' => 'Status',
        ],
    ],

    'brcode_payments' => [
        'columns' => [
            'tax_id' => 'Receiver tax id',
            'destined_status' => 'Armed destination',
            'failure_reason' => 'Refusal reason',
            'held' => 'Held',
        ],
        'actions' => [
            'force_status' => 'Force status',
            'force_status_notification' => 'Status forced',
            'status_field' => 'Status',
        ],
    ],

    'dict_entries' => [
        'columns' => [
            'pix_key' => 'PIX key',
            'tax_id' => 'Tax id',
            'bank_name' => 'Bank',
            'ispb' => 'ISPB',
            'account_type' => 'Account type',
        ],
        'actions' => [
            'register' => 'Register key',
            'register_notification' => 'PIX key registered',
        ],
    ],

    'webhook_emissions' => [
        'columns' => [
            'event_id' => 'Event id',
            'entity_id' => 'Entity id',
            'response_code' => 'HTTP',
            'sent_at' => 'Delivered at',
            'held' => 'Held',
            'failed_reason' => 'Failure',
        ],
        'actions' => [
            'replay' => 'Replay',
            'replay_description' => "Resends the stored bytes with the same event.id — exercises the consumer's idempotency.",
            'replay_notification' => 'Emission replayed',
            'emit_corrupted' => 'Emit corrupted',
            'emit_corrupted_description' => 'Emits the same subscription/event pair signed with another key — the consumer should refuse with 401.',
            'emit_corrupted_notification' => 'Corrupted emission fired',
            'release_hold' => 'Release hold',
            'release_hold_description' => 'Delivers now the emission the HoldNext scenario held back.',
            'release_hold_notification' => 'Emission released',
        ],
    ],
];
