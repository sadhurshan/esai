<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Settings\UpdateCompanyAiSettingsRequest;
use App\Http\Resources\Settings\CompanyAiSettingsResource;
use App\Models\AiEvent;
use App\Models\CompanyAiSetting;
use App\Services\Ai\AiEventRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyAiSettingsController extends ApiController
{
    public function __construct(private readonly AiEventRecorder $recorder)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $company = $user->company;
        if ($company === null) {
            return $this->fail('Company context required.', 403);
        }

        $settings = CompanyAiSetting::query()->firstOrCreate(
            ['company_id' => $company->id],
            [
                'llm_answers_enabled' => false,
                'llm_provider' => 'dummy',
            ],
        );

        return $this->ok((new CompanyAiSettingsResource($settings))->toArray($request));
    }

    public function update(UpdateCompanyAiSettingsRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $company = $user->company;
        if ($company === null) {
            return $this->fail('Company context required.', 403);
        }

        $settings = CompanyAiSetting::query()->firstOrNew(['company_id' => $company->id]);
        $before = [
            'llm_answers_enabled' => (bool) $settings->llm_answers_enabled,
            'llm_provider' => $settings->llm_provider ?? 'dummy',
        ];

        $enabled = (bool) $request->validated('llm_answers_enabled');
        $settings->fill([
            'llm_answers_enabled' => $enabled,
            'llm_provider' => $enabled ? 'openai' : 'dummy',
        ]);
        $settings->save();

        if ($settings->wasRecentlyCreated || $settings->wasChanged(['llm_answers_enabled', 'llm_provider'])) {
            $this->recorder->record(
                companyId: $company->id,
                userId: $user->id,
                feature: 'llm_answers_toggle',
                requestPayload: [
                    'llm_answers_enabled' => $settings->llm_answers_enabled,
                    'llm_provider' => $settings->llm_provider,
                ],
                responsePayload: [
                    'previous_state' => $before,
                ],
                status: AiEvent::STATUS_SUCCESS,
            );
        }

        return $this->ok(
            (new CompanyAiSettingsResource($settings))->toArray($request),
            'AI settings updated.',
        );
    }
}
