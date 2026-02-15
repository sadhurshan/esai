<?php

namespace App\Services\Ai;

use App\Enums\ScrapedSupplierStatus;
use App\Enums\SupplierScrapeJobStatus;
use App\Jobs\PollSupplierScrapeJob;
use App\Models\AiEvent;
use App\Models\SupplierScrapeJob;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SupplierScrapeService
{
    private const MAX_RESULTS = 25;
    private const MIN_RESULTS = 1;

    public function __construct(
        private readonly AiClient $client,
        private readonly AiEventRecorder $recorder,
    ) {
    }

    public function startScrape(?int $companyId, string $query, ?string $region, int $maxResults): SupplierScrapeJob
    {
        $normalizedQuery = $this->sanitizeString($query);
        if ($normalizedQuery === null) {
            throw new RuntimeException('Search query is required.');
        }

        $normalizedRegion = $this->sanitizeString($region, allowEmpty: false);
        $maxResults = $this->normalizeMaxResults($maxResults);
        $userId = auth()->id();

        return CompanyContext::forCompany($companyId, function () use ($companyId, $normalizedQuery, $normalizedRegion, $maxResults, $userId): SupplierScrapeJob {
            $job = SupplierScrapeJob::query()->create([
                'company_id' => $companyId,
                'user_id' => $userId,
                'query' => $normalizedQuery,
                'region' => $normalizedRegion,
                'status' => SupplierScrapeJobStatus::Pending,
                'parameters_json' => [
                    'max_results' => $maxResults,
                ],
            ]);

            $payload = array_filter([
                'query' => $normalizedQuery,
                'region' => $normalizedRegion,
                'max_results' => $maxResults,
            ], static fn ($value) => $value !== null && $value !== '');

            try {
                $response = $this->client->scrapeSuppliers($payload);
                $remoteJobId = $response['job_id'] ?? null;

                if ($remoteJobId === null) {
                    throw new RuntimeException('AI service did not return a scrape job identifier.');
                }

                $this->updateJobParameters($job, [
                    'remote_job_id' => $remoteJobId,
                    'max_results' => $maxResults,
                ]);

                $this->recordEvent(
                    job: $job,
                    feature: 'supplier_scrape_start',
                    requestPayload: $payload,
                    responsePayload: $response['response'] ?? null,
                );

                $job->refresh();
                PollSupplierScrapeJob::dispatch($job);

                return $job;
            } catch (Throwable $exception) {
                $job->update([
                    'status' => SupplierScrapeJobStatus::Failed,
                    'error_message' => $exception->getMessage(),
                    'finished_at' => now(),
                ]);

                $this->recordEvent(
                    job: $job,
                    feature: 'supplier_scrape_start',
                    requestPayload: $payload,
                    responsePayload: null,
                    status: AiEvent::STATUS_ERROR,
                    errorMessage: $exception->getMessage(),
                );

                throw $exception;
            }
        });
    }

    public function refreshScrapeStatus(SupplierScrapeJob $job): void
    {
        $remoteJobId = $this->extractRemoteJobId($job);

        $response = $this->client->getScrapeJob($remoteJobId);
        $remoteJob = $response['job'] ?? null;

        if (! is_array($remoteJob)) {
            throw new RuntimeException('AI service returned an invalid scrape job payload.');
        }

        $status = $this->applyRemoteJobState($job, $remoteJob);

        $this->recordEvent(
            job: $job,
            feature: 'supplier_scrape_status',
            requestPayload: ['remote_job_id' => $remoteJobId],
            responsePayload: $remoteJob,
            status: $status === SupplierScrapeJobStatus::Failed ? AiEvent::STATUS_ERROR : AiEvent::STATUS_SUCCESS,
            errorMessage: $job->error_message,
        );

        if ($status !== SupplierScrapeJobStatus::Completed) {
            return;
        }

        $records = $this->collectRemoteResults($remoteJobId);
        Log::info('supplier_scrape_raw_results', [
            'job_id' => $job->id,
            'remote_job_id' => $remoteJobId,
            'records' => $records,
        ]);
        $this->persistScrapedSuppliers($job, $records);

        if ($job->finished_at === null) {
            $job->finished_at = now();
            $job->save();
        }

        $this->recordEvent(
            job: $job,
            feature: 'supplier_scrape_results',
            requestPayload: ['remote_job_id' => $remoteJobId],
            responsePayload: [
                'count' => count($records),
            ],
        );
    }

    public function recordTimeout(SupplierScrapeJob $job, string $message): void
    {
        $this->recordEvent(
            job: $job,
            feature: 'supplier_scrape_timeout',
            requestPayload: [
                'remote_job_id' => $this->extractRemoteJobId($job),
                'timeout_seconds' => config('ai.scraper.max_duration_seconds'),
            ],
            responsePayload: null,
            status: AiEvent::STATUS_ERROR,
            errorMessage: $message,
        );
    }

    private function applyRemoteJobState(SupplierScrapeJob $job, array $remoteJob): SupplierScrapeJobStatus
    {
        $statusValue = $this->sanitizeString($remoteJob['status'] ?? null);
        $status = SupplierScrapeJobStatus::tryFrom($statusValue ?? '') ?? $job->status ?? SupplierScrapeJobStatus::Pending;

        $job->status = $status;
        $job->result_count = $this->toInteger($remoteJob['result_count'] ?? null, $job->result_count ?? 0);
        $job->error_message = $this->sanitizeString($remoteJob['error_message'] ?? null, allowEmpty: false);

        if ($startedAt = $this->parseTimestamp($remoteJob['started_at'] ?? null)) {
            $job->started_at = $startedAt;
        }

        if ($finishedAt = $this->parseTimestamp($remoteJob['finished_at'] ?? null)) {
            $job->finished_at = $finishedAt;
        }

        $job->save();

        return $status;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectRemoteResults(string $remoteJobId): array
    {
        $offset = 0;
        $records = [];

        while (true) {
            $batch = $this->client->getScrapeJobResults($remoteJobId, $offset, 25);
            $items = $batch['items'] ?? [];
            $meta = $batch['meta'] ?? [];

            if (is_array($items)) {
                foreach ($items as $item) {
                    if (is_array($item)) {
                        $records[] = $item;
                    }
                }
            }

            $nextOffset = $meta['next_offset'] ?? null;

            if (! is_int($nextOffset)) {
                break;
            }

            if ($nextOffset === $offset) {
                break;
            }

            $offset = $nextOffset;
        }

        return $records;
    }

    /**
     * @param list<array<string, mixed>> $records
     */
    private function persistScrapedSuppliers(SupplierScrapeJob $job, array $records): void
    {
        CompanyContext::forCompany($job->company_id, function () use ($job, $records): void {
            DB::transaction(function () use ($job, $records): void {
                $job->scrapedSuppliers()->delete();

                foreach ($records as $index => $record) {
                    $job->scrapedSuppliers()->create([
                        'company_id' => $job->company_id,
                        'scrape_job_id' => $job->id,
                        'name' => $this->sanitizeString($record['name'] ?? null) ?? sprintf('Supplier %d', $index + 1),
                        'website' => $this->sanitizeString($record['website'] ?? null),
                        'description' => $this->sanitizeString($record['description'] ?? null, allowEmpty: true),
                        'industry_tags' => $this->normalizeArray($record['industry_tags'] ?? null),
                        'address' => $this->sanitizeString($record['address'] ?? null, allowEmpty: true),
                        'city' => $this->sanitizeString($record['city'] ?? null, allowEmpty: true),
                        'state' => $this->sanitizeString($record['state'] ?? null, allowEmpty: true),
                        'country' => $this->sanitizeString($record['country'] ?? null, allowEmpty: true),
                        'phone' => $this->sanitizeString($record['phone'] ?? null, allowEmpty: true),
                        'email' => $this->sanitizeString($record['email'] ?? null, allowEmpty: true),
                        'contact_person' => $this->sanitizeString($record['contact_person'] ?? null, allowEmpty: true),
                        'certifications' => $this->normalizeArray($record['certifications'] ?? null),
                        'product_summary' => $this->sanitizeString($record['product_summary'] ?? null, allowEmpty: true),
                        'source_url' => $this->sanitizeString($record['source_url'] ?? null),
                        'confidence' => $this->normalizeConfidence($record['confidence'] ?? null),
                        'metadata_json' => $this->normalizeMetadata($record['metadata_json'] ?? null),
                        'status' => ScrapedSupplierStatus::Pending,
                    ]);
                }
            });

            $job->result_count = count($records);
            $job->save();
        });
    }

    private function normalizeConfidence(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            $confidence = (float) $value;

            return max(0.0, min(1.0, $confidence));
        }

        return null;
    }

    private function normalizeArray(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $items = [];

            foreach ($value as $entry) {
                $stringEntry = $this->sanitizeString($entry, allowEmpty: false);

                if ($stringEntry !== null) {
                    $items[] = $stringEntry;
                }
            }

            return $items !== [] ? $items : null;
        }

        return null;
    }

    private function normalizeMetadata(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        return is_array($value) ? $value : null;
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

    private function normalizeMaxResults(int $maxResults): int
    {
        return max(self::MIN_RESULTS, min(self::MAX_RESULTS, $maxResults));
    }

    private function toInteger(mixed $value, int $default = 0): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function extractRemoteJobId(SupplierScrapeJob $job): string
    {
        $parameters = $job->parameters_json ?? [];
        $remoteId = is_array($parameters) ? $parameters['remote_job_id'] ?? null : null;
        $remoteId = $this->sanitizeString($remoteId);

        if ($remoteId === null) {
            throw new RuntimeException('Supplier scrape job is missing a remote identifier.');
        }

        return $remoteId;
    }

    private function updateJobParameters(SupplierScrapeJob $job, array $overrides): void
    {
        $parameters = $job->parameters_json;
        $parameters = is_array($parameters) ? $parameters : [];

        $job->parameters_json = array_merge($parameters, $overrides);
        $job->save();
    }

    private function recordEvent(
        SupplierScrapeJob $job,
        string $feature,
        array $requestPayload,
        ?array $responsePayload = null,
        string $status = AiEvent::STATUS_SUCCESS,
        ?string $errorMessage = null
    ): void {
        $this->recorder->record(
            companyId: $job->company_id,
            userId: $job->user_id,
            feature: $feature,
            requestPayload: array_merge($requestPayload, [
                'job_id' => $job->id,
            ]),
            responsePayload: $responsePayload,
            latencyMs: null,
            status: $status,
            errorMessage: $errorMessage,
            entityType: SupplierScrapeJob::class,
            entityId: $job->id,
        );
    }
}
