<?php

use Illuminate\Support\Facades\Artisan;

it('reports zero warnings for /v1/ai routes', function (): void {
    $exitCode = Artisan::call('ai:audit-permissions');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('AI RBAC audit passed: 0 warnings.');
});
