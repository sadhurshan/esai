<?php

use App\Enums\DigitalTwinStatus;
use App\Enums\DigitalTwinVisibility;
use App\Models\DigitalTwin;
use App\Http\Middleware\EnsureBuyerAccess;
use App\Http\Middleware\EnsureCompanyOnboarded;
use App\Http\Middleware\EnsureDigitalTwinAccess;
use App\Http\Middleware\EnsureSubscribed;
use Illuminate\Auth\Middleware\Authenticate;
use function Pest\Laravel\getJson;
use function Pest\Laravel\withoutMiddleware;

test('library hides unpublished digital twins with envelope errors', function (): void {
    $twin = DigitalTwin::factory()->create([
        'status' => DigitalTwinStatus::Draft,
        'visibility' => DigitalTwinVisibility::Private,
    ]);

    withoutMiddleware([
        Authenticate::class,
        EnsureCompanyOnboarded::class,
        EnsureSubscribed::class,
        EnsureDigitalTwinAccess::class,
        EnsureBuyerAccess::class,
    ]);

    getJson("/api/library/digital-twins/{$twin->id}")
        ->assertStatus(404)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Digital twin not found.');
});
