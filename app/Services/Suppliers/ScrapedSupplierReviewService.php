<?php

namespace App\Services\Suppliers;

use App\Actions\Supplier\StoreSupplierDocumentAction;
use App\Enums\ScrapedSupplierStatus;
use App\Models\ScrapedSupplier;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Ai\AiEventRecorder;
use App\Support\CompanyContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ScrapedSupplierReviewService
{
    public function __construct(
        private readonly AiEventRecorder $eventRecorder,
        private readonly StoreSupplierDocumentAction $storeSupplierDocumentAction,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function approve(ScrapedSupplier $scrapedSupplier, array $payload, User $reviewer, ?UploadedFile $attachment = null): Supplier
    {
        return CompanyContext::forCompany($scrapedSupplier->company_id, function () use ($scrapedSupplier, $payload, $reviewer, $attachment): Supplier {
            return DB::transaction(function () use ($scrapedSupplier, $payload, $reviewer, $attachment): Supplier {
                $this->assertPending($scrapedSupplier);

                $attributes = $this->buildSupplierAttributes($scrapedSupplier, $payload);
                $supplier = Supplier::query()->create($attributes);

                if ($attachment !== null) {
                    $type = (string) ($payload['attachment_type'] ?? 'other');
                    $this->storeSupplierDocumentAction->execute(
                        $supplier,
                        $reviewer,
                        $attachment,
                        $type,
                        null,
                        null,
                    );
                }

                $scrapedSupplier->status = ScrapedSupplierStatus::Approved;
                $scrapedSupplier->approved_supplier_id = $supplier->id;
                $scrapedSupplier->reviewed_by = $reviewer->id;
                $scrapedSupplier->reviewed_at = now();
                $scrapedSupplier->review_notes = $this->sanitizeString($payload['notes'] ?? null, true);
                $scrapedSupplier->save();

                $this->eventRecorder->record(
                    companyId: (int) $scrapedSupplier->company_id,
                    userId: $reviewer->id,
                    feature: 'supplier_scrape_approve',
                    requestPayload: [
                        'scraped_supplier_id' => $scrapedSupplier->id,
                        'supplier_id' => $supplier->id,
                        'notes' => $scrapedSupplier->review_notes,
                    ],
                    responsePayload: [
                        'status' => $scrapedSupplier->status?->value,
                    ],
                    entityType: ScrapedSupplier::class,
                    entityId: $scrapedSupplier->id,
                );

                return $supplier;
            });
        });
    }

    public function discard(ScrapedSupplier $scrapedSupplier, ?string $notes, User $reviewer): ScrapedSupplier
    {
        return CompanyContext::forCompany($scrapedSupplier->company_id, function () use ($scrapedSupplier, $notes, $reviewer): ScrapedSupplier {
            return DB::transaction(function () use ($scrapedSupplier, $notes, $reviewer): ScrapedSupplier {
                $this->assertPending($scrapedSupplier);

                $scrapedSupplier->status = ScrapedSupplierStatus::Discarded;
                $scrapedSupplier->approved_supplier_id = null;
                $scrapedSupplier->reviewed_by = $reviewer->id;
                $scrapedSupplier->reviewed_at = now();
                $scrapedSupplier->review_notes = $this->sanitizeString($notes, true);
                $scrapedSupplier->save();

                $this->eventRecorder->record(
                    companyId: (int) $scrapedSupplier->company_id,
                    userId: $reviewer->id,
                    feature: 'supplier_scrape_discard',
                    requestPayload: [
                        'scraped_supplier_id' => $scrapedSupplier->id,
                        'notes' => $scrapedSupplier->review_notes,
                    ],
                    responsePayload: [
                        'status' => $scrapedSupplier->status?->value,
                    ],
                    entityType: ScrapedSupplier::class,
                    entityId: $scrapedSupplier->id,
                );

                return $scrapedSupplier;
            });
        });
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function buildSupplierAttributes(ScrapedSupplier $scrapedSupplier, array $payload): array
    {
        $address = $this->sanitizeString($payload['address'] ?? $scrapedSupplier->address, true);

        return array_filter([
            'company_id' => $scrapedSupplier->company_id,
            'name' => $this->sanitizeString($payload['name'] ?? $scrapedSupplier->name) ?? $scrapedSupplier->name,
            'website' => $this->sanitizeString($payload['website'] ?? $scrapedSupplier->website, true),
            'email' => $this->sanitizeString($payload['email'] ?? $scrapedSupplier->email, true),
            'phone' => $this->sanitizeString($payload['phone'] ?? $scrapedSupplier->phone, true),
            'address' => $address,
            'city' => $this->sanitizeString($payload['city'] ?? $scrapedSupplier->city, true),
            'country' => $this->sanitizeString($payload['country'] ?? $scrapedSupplier->country, true),
            'status' => 'approved',
            'capabilities' => $this->buildCapabilitiesPayload($scrapedSupplier, $payload),
            'lead_time_days' => $this->normalizeInt($payload['lead_time_days'] ?? null),
            'moq' => $this->normalizeInt($payload['moq'] ?? null),
        ], static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildCapabilitiesPayload(ScrapedSupplier $scrapedSupplier, array $payload): ?array
    {
        $capabilities = Arr::wrap($payload['capabilities'] ?? []);

        foreach (['methods', 'materials', 'finishes', 'industries', 'tolerances'] as $key) {
            if (isset($capabilities[$key])) {
                $capabilities[$key] = $this->normalizeStringArray($capabilities[$key]);
            }
        }

        if (! isset($capabilities['industries']) && is_array($scrapedSupplier->industry_tags)) {
            $capabilities['industries'] = $this->normalizeStringArray($scrapedSupplier->industry_tags);
        }

        $summary = $payload['product_summary'] ?? $capabilities['summary'] ?? $scrapedSupplier->product_summary;
        if ($summary) {
            $capabilities['summary'] = $this->sanitizeString($summary, true);
        }

        if (! empty($payload['certifications'])) {
            $capabilities['certifications'] = $this->normalizeStringArray($payload['certifications']);
        } elseif (is_array($scrapedSupplier->certifications) && $scrapedSupplier->certifications !== []) {
            $capabilities['certifications'] = $this->normalizeStringArray($scrapedSupplier->certifications);
        }

        if ($scrapedSupplier->source_url) {
            $sources = Arr::wrap($capabilities['sources'] ?? []);
            $sources[] = $scrapedSupplier->source_url;
            $capabilities['sources'] = array_values(array_filter(array_unique($sources)));
        }

        return $capabilities === [] ? null : $capabilities;
    }

    private function normalizeStringArray(mixed $values): array
    {
        $items = Arr::wrap($values);

        $normalized = array_values(array_filter(array_map(
            fn ($value) => $this->sanitizeString($value),
            $items
        )));

        return $normalized;
    }

    private function sanitizeString(mixed $value, bool $allowEmpty = false): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        if ($string === '' && ! $allowEmpty) {
            return null;
        }

        return $string === '' ? null : $string;
    }

    private function normalizeInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function assertPending(ScrapedSupplier $scrapedSupplier): void
    {
        $status = $scrapedSupplier->status ?? ScrapedSupplierStatus::Pending;

        if ($status instanceof ScrapedSupplierStatus && $status->isFinal()) {
            throw new RuntimeException('Scraped supplier has already been reviewed.');
        }
    }
}
