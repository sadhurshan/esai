<?php

namespace App\Jobs;

use App\Enums\RiskGrade;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierRiskScore;
use App\Services\SupplierRiskService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ComputeSupplierRiskScoresJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const SUPPLIER_CHUNK = 100;

    public function handle(SupplierRiskService $supplierRiskService): void
    {
        $periodStart = Carbon::now()->copy()->startOfMonth();
        $periodEnd = Carbon::now()->copy()->endOfDay();

        Company::query()
            ->select('id')
            ->chunkById(50, function ($companies) use ($supplierRiskService, $periodStart, $periodEnd): void {
                foreach ($companies as $company) {
                    $this->scoreCompany((int) $company->id, $periodStart, $periodEnd, $supplierRiskService);
                }
            });
    }

    private function scoreCompany(
        int $companyId,
        Carbon $periodStart,
        Carbon $periodEnd,
        SupplierRiskService $supplierRiskService
    ): void {
        Supplier::query()
            ->where('company_id', $companyId)
            ->with('company')
            ->chunkById(self::SUPPLIER_CHUNK, function ($suppliers) use ($periodStart, $periodEnd, $supplierRiskService): void {
                foreach ($suppliers as $supplier) {
                    $this->scoreSupplier($supplier, $periodStart, $periodEnd, $supplierRiskService);
                }
            });
    }

    private function scoreSupplier(
        Supplier $supplier,
        Carbon $periodStart,
        Carbon $periodEnd,
        SupplierRiskService $supplierRiskService
    ): void {
        try {
            $score = $supplierRiskService->calculateForSupplier($supplier, $periodStart, $periodEnd);
        } catch (Throwable $exception) {
            Log::warning('Failed to compute supplier risk features', [
                'supplier_id' => $supplier->id,
                'company_id' => $supplier->company_id,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        $features = $this->buildFeaturePayload($score);

        if (empty($features)) {
            Log::info('Skipping supplier risk scoring due to missing features', [
                'supplier_id' => $supplier->id,
                'company_id' => $supplier->company_id,
            ]);

            return;
        }

        $prediction = $this->requestSupplierRisk($supplier->company_id, $supplier->id, $features);

        if ($prediction === null) {
            return;
        }

        $this->persistSupplierRiskResult($score, $supplier, $prediction);
    }

    /**
     * @return array<string, float|null>
     */
    private function buildFeaturePayload(SupplierRiskScore $score): array
    {
        return [
            'supplier_id' => $score->supplier_id,
            'company_id' => $score->company_id,
            'on_time_rate' => $this->nullableFloat($score->on_time_delivery_rate),
            'defect_rate' => $this->nullableFloat($score->defect_rate),
            'lead_time_variance' => $this->nullableFloat($score->lead_time_volatility),
            'price_volatility' => $this->nullableFloat($score->price_volatility),
            'service_responsiveness' => $this->nullableFloat($score->responsiveness_rate),
        ];
    }

    private function requestSupplierRisk(int $companyId, int $supplierId, array $payload): ?array
    {
        $baseUrl = rtrim((string) config('services.ai_microservice.base_url', ''), '/');

        if ($baseUrl === '') {
            return null;
        }

        $timeout = (int) config('services.ai_microservice.timeout', 10);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->post(sprintf('%s/supplier-risk', $baseUrl), [
                    'supplier' => $payload,
                ]);
        } catch (Throwable $exception) {
            Log::warning('AI supplier risk request failed', [
                'supplier_id' => $supplierId,
                'company_id' => $companyId,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('AI supplier risk request returned error', [
                'supplier_id' => $supplierId,
                'company_id' => $companyId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json('data');

        return is_array($data) ? $data : null;
    }

    private function persistSupplierRiskResult(SupplierRiskScore $score, Supplier $supplier, array $prediction): void
    {
        $riskScore = isset($prediction['risk_score']) ? (float) $prediction['risk_score'] : null;
        $riskCategory = strtolower((string) ($prediction['risk_category'] ?? ''));
        $explanation = (string) ($prediction['explanation'] ?? '');

        if ($riskScore !== null) {
            $score->overall_score = round(max(0.0, min(1.0, $riskScore)), 4);
        }

        $grade = RiskGrade::tryFrom($riskCategory);

        if ($grade !== null) {
            $score->risk_grade = $grade;
            $supplier->risk_grade = $grade;
        }

        $meta = $score->meta ?? [];
        $meta['ai'] = [
            'explanation' => $explanation,
            'synced_at' => Carbon::now()->toIso8601String(),
        ];
        $score->meta = $meta;

        $score->save();

        if ($supplier->isDirty('risk_grade')) {
            $supplier->save();
        }
    }

    private function nullableFloat(null|int|float|string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if ($value === '') {
            return null;
        }

        return (float) $value;
    }
}
