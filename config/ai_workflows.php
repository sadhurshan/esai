<?php

return [
    'start_permissions' => ['ai.workflows.run'],
    'approve_permissions' => ['ai.workflows.approve'],
    'templates' => [
        'procurement' => [
            [
                'action_type' => 'rfq_draft',
                'name' => 'RFQ Draft',
                'approval_permissions' => ['rfqs.write'],
            ],
            [
                'action_type' => 'compare_quotes',
                'name' => 'Quote Comparison',
                'approval_permissions' => ['rfqs.write'],
            ],
            [
                'action_type' => 'award_quote',
                'name' => 'Award Quote',
                'approval_permissions' => ['quotes.write'],
            ],
            [
                'action_type' => 'po_draft',
                'name' => 'Purchase Order Draft',
                'approval_permissions' => ['orders.write'],
            ],
        ],
        'procurement_full_flow' => [
            [
                'action_type' => 'rfq_draft',
                'name' => 'RFQ Draft',
                'approval_permissions' => ['rfqs.write'],
            ],
            [
                'action_type' => 'compare_quotes',
                'name' => 'Quote Comparison',
                'approval_permissions' => ['rfqs.write'],
            ],
            [
                'action_type' => 'award_quote',
                'name' => 'Award Quote',
                'approval_permissions' => ['quotes.write'],
            ],
            [
                'action_type' => 'po_draft',
                'name' => 'Purchase Order Draft',
                'approval_permissions' => ['orders.write'],
            ],
            [
                'action_type' => 'receiving_quality',
                'name' => 'Receiving & Quality Review',
                'approval_permissions' => ['receiving.write'],
            ],
            [
                'action_type' => 'invoice_approval',
                'name' => 'Invoice Approval',
                'approval_permissions' => ['finance.write'],
            ],
            [
                'action_type' => 'payment_process',
                'name' => 'Payment Processing',
                'approval_permissions' => ['finance.write'],
            ],
        ],
        'receiving_quality' => [
            [
                'action_type' => 'receiving_quality',
                'name' => 'Receiving & Quality Review',
                'approval_permissions' => ['receiving.write'],
            ],
        ],
        'invoice_approval_flow' => [
            [
                'action_type' => 'invoice_draft',
                'name' => 'Invoice Draft',
                'approval_permissions' => ['finance.write'],
            ],
            [
                'action_type' => 'payment_process',
                'name' => 'Payment Processing',
                'approval_permissions' => ['finance.write'],
            ],
        ],
    ],
];
