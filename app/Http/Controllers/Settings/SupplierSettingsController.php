<?php

namespace App\Http\Controllers\Settings;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierSettingsController
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        abort_if($user === null, 403);

        $company = $user->company;

        abort_if($company === null, 403, 'Company context required.');

        return Inertia::render('settings/supplier/index', [
            'supplierStatus' => $company->supplier_status instanceof \BackedEnum ? $company->supplier_status->value : $company->supplier_status,
            'directoryVisibility' => $company->directory_visibility,
            'supplierProfileCompletedAt' => optional($company->supplier_profile_completed_at)?->toIso8601String(),
            'isSupplierApproved' => $company->isSupplierApproved(),
            'isSupplierListed' => $company->isSupplierListed(),
            'canToggleVisibility' => in_array($user->role, ['owner', 'buyer_admin'], true) || $user->id === $company->owner_user_id,
        ]);
    }
}
