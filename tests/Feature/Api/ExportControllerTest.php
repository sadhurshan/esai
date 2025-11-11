<?php

use App\Enums\ExportRequestStatus;
use App\Enums\ExportRequestType;
use App\Jobs\ProcessExportRequestJob;
use App\Models\AuditLog;
use App\Models\ExportRequest;
use App\Services\ExportService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

it('queues export request and dispatches processing job', function (): void {
    Bus::fake();
    Storage::fake('exports');

    $user = createExportFeatureUser();

    $response = $this->postJson('/api/exports', [
        'type' => ExportRequestType::FullData->value,
    ]);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Export request queued.')
        ->assertJsonPath('data.status', ExportRequestStatus::Pending->value);

    $exportRequestId = $response->json('data.id');

    expect(ExportRequest::query()->whereKey($exportRequestId)->exists())->toBeTrue();

    Bus::assertDispatched(ProcessExportRequestJob::class, function (ProcessExportRequestJob $job) use ($exportRequestId): bool {
        return $job->exportRequestId === $exportRequestId;
    });

    $auditRecord = AuditLog::query()
        ->where('entity_type', (new ExportRequest())->getMorphClass())
        ->where('entity_id', $exportRequestId)
        ->where('action', 'created')
        ->first();

    expect($auditRecord)->not()->toBeNull();
});

it('downloads completed export archive and logs audit entry', function (): void {
    Storage::fake('exports');

    $user = createExportFeatureUser();

    $exportRequest = ExportRequest::query()->create([
        'company_id' => $user->company_id,
        'requested_by' => $user->id,
        'type' => ExportRequestType::FullData,
        'status' => ExportRequestStatus::Completed,
        'file_path' => $user->company_id.'/export-latest.zip',
        'expires_at' => now()->addDay(),
        'completed_at' => now()->subMinute(),
    ]);

    Storage::disk('exports')->put($exportRequest->file_path, 'zip-content');

    $signedUrl = app(ExportService::class)->generateSignedUrl($exportRequest);

    expect($signedUrl)->not()->toBeNull();

    $response = $this->get($signedUrl);

    $response->assertOk();

    /** @var \Symfony\Component\HttpFoundation\BinaryFileResponse $binary */
    $binary = $response->baseResponse;
    expect($binary)->toBeInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class);
    expect(file_get_contents($binary->getFile()->getPathname()))->toBe('zip-content');

    $auditLog = AuditLog::query()
        ->where('entity_type', $exportRequest->getMorphClass())
        ->where('entity_id', $exportRequest->id)
        ->where('action', 'updated')
        ->where('after->event', 'export_downloaded')
        ->first();

    expect($auditLog)->not()->toBeNull()
        ->and(data_get($auditLog->after, 'context.file_path'))->toBe($exportRequest->file_path);
});

it('rejects audit export requests beyond plan history window', function (): void {
    Bus::fake();
    Storage::fake('exports');

    $user = createExportFeatureUser([
        'export_history_days' => 30,
    ]);

    $response = $this->postJson('/api/exports', [
        'type' => ExportRequestType::AuditLogs->value,
        'filters' => [
            'from' => now()->subDays(60)->toDateString(),
            'to' => now()->toDateString(),
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Validation failed');

    Bus::assertNotDispatched(ProcessExportRequestJob::class);
});

it('requires enabled plan for export requests', function (): void {
    Bus::fake();

    $user = createExportFeatureUser([
        'exports_enabled' => false,
    ]);

    $response = $this->postJson('/api/exports', [
        'type' => ExportRequestType::FullData->value,
    ]);

    $response->assertStatus(402)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Upgrade required to access data exports.');

    Bus::assertNotDispatched(ProcessExportRequestJob::class);
});

it('prevents downloading expired or failed exports', function (): void {
    Storage::fake('exports');

    $user = createExportFeatureUser();

    $expired = ExportRequest::query()->create([
        'company_id' => $user->company_id,
        'requested_by' => $user->id,
        'type' => ExportRequestType::FullData,
        'status' => ExportRequestStatus::Completed,
        'file_path' => $user->company_id.'/expired.zip',
        'expires_at' => now()->subDay(),
        'completed_at' => now()->subDays(2),
    ]);

    Storage::disk('exports')->put($expired->file_path, 'zip-content');

    $expiredUrl = URL::temporarySignedRoute('exports.download', now()->addMinutes(5), [
        'exportRequest' => $expired->id,
    ]);

    $this->get($expiredUrl)
        ->assertStatus(410)
        ->assertJsonPath('message', 'Export file has expired. Request a new export.');

    $failed = ExportRequest::query()->create([
        'company_id' => $user->company_id,
        'requested_by' => $user->id,
        'type' => ExportRequestType::FullData,
        'status' => ExportRequestStatus::Failed,
        'file_path' => null,
        'error_message' => 'Unable to collect dataset.',
    ]);

    $failedUrl = URL::temporarySignedRoute('exports.download', now()->addMinutes(5), [
        'exportRequest' => $failed->id,
    ]);

    $this->get($failedUrl)
        ->assertStatus(409)
        ->assertJsonPath('message', 'Export is not ready for download.');
});
