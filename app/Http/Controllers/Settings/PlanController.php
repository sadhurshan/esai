<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $company = $user?->company;

        abort_if($company === null, 404);

        $company->loadMissing('plan');

        return Inertia::render('settings/plan', [
            'plan' => $company->plan?->only([
                'code',
                'name',
                'rfqs_per_month',
                'users_max',
                'storage_gb',
            ]),
            'status' => $company->billingStatus(),
            'trialEndsAt' => optional($company->trial_ends_at)?->toIso8601String(),
            'upgradeUrl' => url('/pricing'),
        ]);
    }
}
