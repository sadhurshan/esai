<?php

return [
    'thresholds' => [
        'purchase_order_high_value' => (float) env('POLICY_PURCHASE_ORDER_LIMIT', 50000),
        'invoice_high_value' => (float) env('POLICY_INVOICE_LIMIT', 25000),
        'payment_high_value' => (float) env('POLICY_PAYMENT_LIMIT', 25000),
    ],
    'supplier' => [
        'max_risk_grade' => env('POLICY_MAX_RISK_GRADE', 'medium'),
        'max_risk_index' => (float) env('POLICY_MAX_RISK_INDEX', 0.25),
    ],
];
