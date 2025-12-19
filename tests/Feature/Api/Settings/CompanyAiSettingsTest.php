<?php

use App\Models\AiEvent;
use function Pest\Laravel\assertDatabaseHas;

it('returns default ai settings for the company', function (): void {
    $user = createLocalizationFeatureUser();

    $response = $this->getJson('/api/settings/ai');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.llm_answers_enabled', false)
        ->assertJsonPath('data.llm_provider', 'dummy');

    assertDatabaseHas('company_ai_settings', [
        'company_id' => $user->company_id,
        'llm_answers_enabled' => false,
    ]);
});

it('updates ai settings and records an event', function (): void {
    $user = createLocalizationFeatureUser();

    $response = $this->patchJson('/api/settings/ai', [
        'llm_answers_enabled' => true,
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'AI settings updated.')
        ->assertJsonPath('data.llm_answers_enabled', true)
        ->assertJsonPath('data.llm_provider', 'openai');

    assertDatabaseHas('company_ai_settings', [
        'company_id' => $user->company_id,
        'llm_answers_enabled' => true,
        'llm_provider' => 'openai',
    ]);

    $event = AiEvent::query()
        ->where('company_id', $user->company_id)
        ->where('feature', 'llm_answers_toggle')
        ->latest('id')
        ->first();

    expect($event)->not->toBeNull();
    expect($event->request_json['llm_answers_enabled'] ?? null)->toBeTrue();
});
