<?php

namespace App\Http\Controllers\Api;

use App\Enums\RiskGrade;
use App\Http\Requests\Risk\GenerateRiskScoresRequest;
use App\Http\Resources\SupplierRiskScoreResource;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierRiskScore;
use App\Services\SupplierRiskService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierRiskController extends ApiController
{
    public function __construct(private readonly SupplierRiskService $riskService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $user->loadMissing('company');
        $company = $user->company;

        if (! $company instanceof Company) {
            return $this->fail('Company context required.', 403);
        }

        $query = SupplierRiskScore::query()
            ->where('company_id', $company->id)
            ->orderByDesc('updated_at');

        $gradeFilter = $request->query('grade');
        if ($gradeFilter !== null) {
            $grade = RiskGrade::tryFrom((string) $gradeFilter);

            if ($grade === null) {
                return $this->fail('Invalid risk grade filter.', 422, [
                    'grade' => ['Grade must be one of: low, medium, high.'],
                ]);
            }

            $query->where('risk_grade', $grade->value);
        }

        $from = $request->query('from');
        $to = $request->query('to');

        if ($from !== null) {
            $fromDate = Carbon::parse($from)->startOfDay();
            $query->where('updated_at', '>=', $fromDate);
        }

        if ($to !== null) {
            $toDate = Carbon::parse($to)->endOfDay();
            $query->where('updated_at', '<=', $toDate);
        }

        $scores = $query->get();

        return $this->ok(
            SupplierRiskScoreResource::collection($scores)->toArray($request),
            'Supplier risk scores retrieved.',
            [
                'count' => $scores->count(),
            ]
        );
    }

    public function show(Request $request, Supplier $supplier): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $user->loadMissing('company');
        $company = $user->company;

        if (! $company instanceof Company || (int) $supplier->company_id !== (int) $company->id) {
            return $this->fail('Supplier not accessible.', 403);
        }

        $score = SupplierRiskScore::query()
            ->where('company_id', $company->id)
            ->where('supplier_id', $supplier->id)
            ->first();

        if ($score === null) {
            return $this->fail('Risk score not available for supplier.', 404);
        }

        return $this->ok((new SupplierRiskScoreResource($score))->toArray($request), 'Supplier risk score retrieved.');
    }

    public function generate(GenerateRiskScoresRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $user->loadMissing('company.plan');
        $company = $user->company;

        if (! $company instanceof Company) {
            return $this->fail('Company context required.', 403);
        }

        $plan = $company->plan;

        if ($plan === null || ! $plan->risk_scores_enabled) {
            return $this->fail('Supplier risk scoring not available on current plan.', 403);
        }

        $historyLimit = max(0, (int) $plan->risk_history_months);

        $period = $request->period();
        $periodKey = $period['key'];

        $existingForPeriod = SupplierRiskScore::query()
            ->where('company_id', $company->id)
            ->where('meta->period_key', $periodKey)
            ->exists();

        if (! $existingForPeriod && $historyLimit > 0 && $company->risk_scores_monthly_used >= $historyLimit) {
            return $this->fail('Upgrade required to generate additional risk score history.', 402);
        }

        $scores = $this->riskService->calculateForCompany($company, $period['start'], $period['end']);

        return $this->ok(
            SupplierRiskScoreResource::collection($scores)->toArray($request),
            'Supplier risk scores generated.',
            [
                'period_start' => $period['start']->toDateString(),
                'period_end' => $period['end']->toDateString(),
                'generated' => $scores->count(),
            ]
        );
    }
}
