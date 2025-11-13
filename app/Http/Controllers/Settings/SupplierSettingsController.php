<?php

namespace App\Http\Controllers\Settings;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SupplierSettingsController
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        abort_if($user === null, 403);

        $company = $user->company;

        abort_if($company === null, 403, 'Company context required.');

        return view('app');
    }
}
