<?php

return [
    'document_expiring_threshold_days' => (int) env('SUPPLIER_DOCUMENT_EXPIRING_THRESHOLD_DAYS', 30),
    'certificate_notification_roles' => [
        'owner',
        'buyer_admin',
        'supplier_admin',
    ],
];
