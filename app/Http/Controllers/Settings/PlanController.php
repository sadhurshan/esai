<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $company = $user?->company;

        abort_if($company === null, 404);

        return view('app');
    }
}
