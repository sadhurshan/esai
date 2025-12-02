<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('settings/{any?}', function (?string $any = null) {
        $suffix = $any !== null && $any !== '' ? '/'.$any : '';

        return redirect("/app/settings{$suffix}");
    })->where('any', '.*');
});
