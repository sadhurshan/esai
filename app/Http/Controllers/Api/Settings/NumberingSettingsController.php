<?php

namespace App\Http\Controllers\Api\Settings;

use App\Enums\DocumentNumberType;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Settings\UpdateNumberingSettingsRequest;
use App\Http\Resources\Settings\NumberingSettingsResource;
use App\Models\CompanyDocumentNumbering;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NumberingSettingsController extends ApiController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($user->company === null) {
            return $this->fail('Company context required.', 403);
        }

        $settings = $this->loadSettings($user->company_id);

        return $this->ok((new NumberingSettingsResource($settings))->toArray($request));
    }

    public function update(UpdateNumberingSettingsRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($user->company === null) {
            return $this->fail('Company context required.', 403);
        }

        $companyId = $user->company_id;
        $payload = $request->payload();

        foreach ($payload as $type => $rulePayload) {
            $rule = CompanyDocumentNumbering::query()->firstOrNew([
                'company_id' => $companyId,
                'document_type' => $type,
            ]);

            $before = $rule->exists ? $rule->getOriginal() : [];

            $rule->fill([
                'prefix' => $rulePayload['prefix'] ?? $rule->prefix,
                'seq_len' => $rulePayload['seq_len'] ?? $rule->seq_len,
                'next' => $rulePayload['next'] ?? $rule->next,
                'reset' => $rulePayload['reset'] ?? $rule->reset?->value,
            ]);

            $dirty = ! $rule->exists || $rule->isDirty();
            $rule->save();

            if ($rule->wasRecentlyCreated) {
                $this->auditLogger->created($rule, $rule->toArray());
            } elseif ($dirty) {
                $this->auditLogger->updated($rule, $before, $rule->toArray());
            }
        }

        $settings = $this->loadSettings($companyId);

        return $this->ok((new NumberingSettingsResource($settings))->toArray($request), 'Numbering settings updated.');
    }

    /**
     * @return array<string, CompanyDocumentNumbering|null>
     */
    private function loadSettings(int $companyId): array
    {
        $existing = CompanyDocumentNumbering::query()
            ->where('company_id', $companyId)
            ->get()
            ->keyBy(function (CompanyDocumentNumbering $model): string {
                $type = $model->document_type;

                return $type instanceof DocumentNumberType ? $type->value : (string) $model->document_type;
            });

        $settings = [];

        foreach (DocumentNumberType::cases() as $type) {
            $settings[$type->value] = $existing->get($type->value);
        }

        return $settings;
    }
}
