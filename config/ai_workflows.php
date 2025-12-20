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
                'action_type' => 'po_draft',
                'name' => 'Purchase Order Draft',
                'approval_permissions' => ['orders.write'],
            ],
        ],
    ],
];
