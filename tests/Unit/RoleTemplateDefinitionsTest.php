<?php

use App\Support\Permissions\RoleTemplateDefinitions;

it('grants supplier admins sourcing read access without rfq writes', function (): void {
    $permissions = RoleTemplateDefinitions::permissionsForRole('supplier_admin');

    expect($permissions)
        ->toContain('rfqs.read')
        ->not->toContain('rfqs.write')
        ->toContain('orders.write');
});

it('prevents supplier estimators from mutating rfqs', function (): void {
    $permissions = RoleTemplateDefinitions::permissionsForRole('supplier_estimator');

    expect($permissions)
        ->toContain('rfqs.read')
        ->not->toContain('rfqs.write');
});

it('allows owners and buyer admins to submit supplier applications', function (): void {
    $ownerPermissions = RoleTemplateDefinitions::permissionsForRole('owner');
    $buyerAdminPermissions = RoleTemplateDefinitions::permissionsForRole('buyer_admin');

    expect($ownerPermissions)
        ->toContain('suppliers.apply');

    expect($buyerAdminPermissions)
        ->toContain('suppliers.apply');
});
