<?php

return [
    'admin_permission_key' => 'admin.console',

    'permission_groups' => [
        [
            'id' => 'platform',
            'label' => 'Platform',
            'description' => 'Administrative capabilities for tenant configuration.',
            'permissions' => [
                [
                    'key' => 'admin.console',
                    'label' => 'Admin Console access',
                    'description' => 'Allows entry to the admin console and elevated settings.',
                    'level' => 'admin',
                    'domain' => 'platform',
                ],
                [
                    'key' => 'plans.manage',
                    'label' => 'Manage plans & limits',
                    'description' => 'Edit plan pricing, feature flags, and tenant assignments.',
                    'level' => 'admin',
                    'domain' => 'platform',
                ],
                [
                    'key' => 'roles.manage',
                    'label' => 'Manage role templates',
                    'description' => 'Adjust platform role templates and permissions.',
                    'level' => 'admin',
                    'domain' => 'platform',
                ],
                [
                    'key' => 'api_keys.manage',
                    'label' => 'Issue API keys',
                    'description' => 'Create, rotate, and revoke tenant API keys.',
                    'level' => 'admin',
                    'domain' => 'platform',
                ],
                [
                    'key' => 'webhooks.manage',
                    'label' => 'Manage webhooks',
                    'description' => 'Create and manage webhook subscriptions and delivery retries.',
                    'level' => 'admin',
                    'domain' => 'platform',
                ],
                [
                    'key' => 'rate_limits.manage',
                    'label' => 'Rate limits',
                    'description' => 'Edit rate limit policies for tenants and APIs.',
                    'level' => 'admin',
                    'domain' => 'platform',
                ],
                [
                    'key' => 'audit.read',
                    'label' => 'View audit log',
                    'description' => 'Inspect audit trails for platform activity.',
                    'level' => 'read',
                    'domain' => 'platform',
                ],
            ],
        ],
        [
            'id' => 'sourcing',
            'label' => 'Sourcing',
            'description' => 'RFQs, quotes, and awards.',
            'permissions' => [
                [
                    'key' => 'rfqs.read',
                    'label' => 'View RFQs',
                    'description' => 'Read RFQs, bids, and statuses.',
                    'level' => 'read',
                    'domain' => 'sourcing',
                ],
                [
                    'key' => 'rfqs.write',
                    'label' => 'Manage RFQs',
                    'description' => 'Create/edit RFQs, invite suppliers, award lines.',
                    'level' => 'write',
                    'domain' => 'sourcing',
                ],
            ],
        ],
        [
            'id' => 'suppliers',
            'label' => 'Suppliers',
            'description' => 'Directory management and onboarding.',
            'permissions' => [
                [
                    'key' => 'suppliers.read',
                    'label' => 'View suppliers',
                    'description' => 'List suppliers, performance, and documents.',
                    'level' => 'read',
                    'domain' => 'suppliers',
                ],
                [
                    'key' => 'suppliers.write',
                    'label' => 'Manage suppliers',
                    'description' => 'Approve, suspend, or edit supplier profiles.',
                    'level' => 'write',
                    'domain' => 'suppliers',
                ],
            ],
        ],
        [
            'id' => 'orders',
            'label' => 'Orders & Fulfillment',
            'description' => 'Purchase orders, receiving, and returns.',
            'permissions' => [
                [
                    'key' => 'orders.read',
                    'label' => 'View orders',
                    'description' => 'Read-only access to purchase orders and deliveries.',
                    'level' => 'read',
                    'domain' => 'orders',
                ],
                [
                    'key' => 'orders.write',
                    'label' => 'Manage orders',
                    'description' => 'Issue, edit, and cancel purchase orders.',
                    'level' => 'write',
                    'domain' => 'orders',
                ],
            ],
        ],
        [
            'id' => 'inventory',
            'label' => 'Inventory',
            'description' => 'Inventory, warehouses, and maintenance.',
            'permissions' => [
                [
                    'key' => 'inventory.read',
                    'label' => 'View inventory',
                    'description' => 'Read inventory balances and transactions.',
                    'level' => 'read',
                    'domain' => 'inventory',
                ],
                [
                    'key' => 'inventory.write',
                    'label' => 'Manage inventory',
                    'description' => 'Adjust stock and configure warehouses.',
                    'level' => 'write',
                    'domain' => 'inventory',
                ],
            ],
        ],
        [
            'id' => 'billing',
            'label' => 'Billing & Finance',
            'description' => 'Invoices, payments, and analytics.',
            'permissions' => [
                [
                    'key' => 'billing.read',
                    'label' => 'View billing data',
                    'description' => 'Access invoices, credit notes, and payment history.',
                    'level' => 'read',
                    'domain' => 'billing',
                ],
                [
                    'key' => 'billing.write',
                    'label' => 'Manage billing',
                    'description' => 'Issue credits, edit invoices, manage tax rules.',
                    'level' => 'write',
                    'domain' => 'billing',
                ],
            ],
        ],
    ],
];
