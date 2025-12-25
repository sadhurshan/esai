<?php

return [
    'middleware_permissions' => [
        'buyer_access' => ['rfqs.write'],
        'ensure.analytics.access' => ['forecasts.read'],
        'billing_access' => ['finance.write'],
        'ensure.ai.admin' => ['ai.admin'],
    ],

    'ai_route_expectations' => [
        [
            'pattern' => 'v1/ai/actions*',
            'required_ensure' => ['ensure.ai.service'],
            'required_permissions' => ['rfqs.write'],
        ],
        [
            'pattern' => 'v1/ai/drafts*',
            'required_ensure' => ['ensure.ai.service'],
            'required_permissions' => ['rfqs.write'],
        ],
        [
            'pattern' => 'v1/ai/chat*',
            'required_ensure' => ['ensure.ai.service'],
            'required_permissions' => ['rfqs.write'],
        ],
        [
            'pattern' => 'v1/ai/workflows*',
            'required_ensure' => ['ensure.ai.service', 'ensure.ai.workflows.access'],
            'required_permissions' => ['rfqs.write'],
        ],
        [
            'pattern' => 'v1/ai/admin/usage-metrics',
            'required_ensure' => ['ensure.ai.service', 'ensure.ai.admin'],
            'required_permissions' => ['ai.admin'],
        ],
    ],

    'ensure_ai_middleware' => [
        'ensure.ai.service',
        'ensure.ai.workflows.access',
        'ensure.ai.training.enabled',
        'ensure.ai.admin',
    ],
];
