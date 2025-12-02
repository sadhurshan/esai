<?php

namespace App\Jobs;

use App\Actions\Suppliers\RequireSupplierReverificationAction;
use App\Models\Company;
use App\Models\SupplierDocument;
use App\Support\Audit\AuditLogger;
use App\Support\Notifications\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

class AuditSupplierDocumentExpiryJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public ?int $companyId = null;

    public function __construct(?int $companyId = null)
    {
        $this->companyId = $companyId;

        $queue = Config::get('queue.names.maintenance', 'maintenance');
        $this->onQueue($queue);
    }

    public function handle(
        NotificationService $notifications,
        AuditLogger $auditLogger,
        RequireSupplierReverificationAction $requireReverification,
    ): void
    {
        $windowDays = (int) Config::get('suppliers.document_expiring_threshold_days', 30);
        $now = Carbon::now()->startOfDay();
        $expiringThreshold = (clone $now)->addDays($windowDays);

        $expiring = [];
        $expired = [];
        $reverificationTriggered = [];

        SupplierDocument::query()
            ->with([
                'supplier:id,name',
                'company:id,name,owner_user_id',
            ])
            ->when($this->companyId !== null, fn ($query) => $query->where('company_id', $this->companyId))
            ->whereNull('deleted_at')
            ->whereNotNull('expires_at')
            ->orderBy('id')
            ->chunkById(200, function (EloquentCollection $documents) use (&$expiring, &$expired, $now, $expiringThreshold, $auditLogger): void {
                foreach ($documents as $document) {
                    $targetStatus = $this->resolveStatus($document->expires_at, $now, $expiringThreshold);

                    if ($targetStatus === $document->status) {
                        continue;
                    }

                    $before = ['status' => $document->status];
                    $document->status = $targetStatus;
                    $document->save();

                    $auditLogger->updated($document, $before, ['status' => $targetStatus]);

                    $payload = $this->buildPayload($document, $targetStatus);

                    if ($targetStatus === 'expiring') {
                        $expiring[$document->company_id][] = $payload;
                    } elseif ($targetStatus === 'expired') {
                        $expired[$document->company_id][] = $payload;
                    }
                }
            });

        $companyIds = array_values(array_unique(array_merge(array_keys($expiring), array_keys($expired))));

        if ($companyIds === []) {
            return;
        }

        $companies = Company::query()
            ->with([
                'owner',
                'users' => function ($query): void {
                    $query->whereIn('role', Config::get('suppliers.certificate_notification_roles', ['owner', 'buyer_admin', 'supplier_admin']));
                },
            ])
            ->whereIn('id', $companyIds)
            ->get()
            ->keyBy('id');

        foreach ($expiring as $companyId => $documents) {
            $company = $companies->get($companyId);
            if ($company === null) {
                continue;
            }

            $recipients = $this->resolveRecipients($company);
            if ($recipients->isEmpty()) {
                continue;
            }

            foreach ($documents as $payload) {
                $this->sendNotification($notifications, $recipients, 'expiring', $payload);
            }
        }

        foreach ($expired as $companyId => $documents) {
            $company = $companies->get($companyId);
            if ($company === null) {
                continue;
            }

            $note = $this->buildReverificationNote($documents);
            $reverificationTriggered[$companyId] = $requireReverification->execute($company, [
                'notes' => $note,
                'document_ids' => $this->extractDocumentIds($documents),
            ]);

            $recipients = $this->resolveRecipients($company);
            if ($recipients->isEmpty()) {
                continue;
            }

            foreach ($documents as $payload) {
                $requiresReverification = $reverificationTriggered[$companyId] ?? false;
                $this->sendNotification($notifications, $recipients, 'expired', $payload, $requiresReverification);
            }
        }
    }

    private function resolveStatus(?Carbon $expiresAt, Carbon $now, Carbon $threshold): string
    {
        if ($expiresAt === null) {
            return 'valid';
        }

        if ($expiresAt->lessThanOrEqualTo($now)) {
            return 'expired';
        }

        if ($expiresAt->lessThanOrEqualTo($threshold)) {
            return 'expiring';
        }

        return 'valid';
    }

    private function buildPayload(SupplierDocument $document, string $status): array
    {
        return [
            'document_id' => $document->id,
            'company_id' => $document->company_id,
            'supplier_id' => $document->supplier_id,
            'supplier_name' => $document->supplier?->name,
            'type' => $document->type,
            'expires_at' => optional($document->expires_at)->toDateString(),
            'status' => $status,
        ];
    }

    private function resolveRecipients(Company $company): Collection
    {
        $allowedRoles = Config::get('suppliers.certificate_notification_roles', ['owner', 'buyer_admin', 'supplier_admin']);
        $recipients = $company->relationLoaded('users') ? $company->users->values() : collect();

        $owner = $company->relationLoaded('owner') ? $company->owner : null;
        if ($owner !== null && in_array($owner->role, $allowedRoles, true) && $recipients->doesntContain(fn ($user) => $user->id === $owner->id)) {
            $recipients->push($owner);
        }

        return $recipients->unique('id');
    }

    private function sendNotification(
        NotificationService $notifications,
        Collection $recipients,
        string $state,
        array $payload,
        bool $requiresReverification = false,
    ): void
    {
        $title = $state === 'expired' ? 'Supplier certificate expired' : 'Supplier certificate expiring soon';

        $supplier = $payload['supplier_name'] ?? 'this supplier';
        $expiresAt = $payload['expires_at'] ?? 'an upcoming date';
        $certificate = strtoupper((string) ($payload['type'] ?? 'document'));

        if ($state === 'expired') {
            $body = $requiresReverification
                ? sprintf('%s for %s expired on %s. Supplier access is paused until a renewed certificate is uploaded and re-verified.', $certificate, $supplier, $expiresAt)
                : sprintf('%s for %s expired on %s. Upload a renewed certificate to restore supplier visibility.', $certificate, $supplier, $expiresAt);
        } else {
            $body = sprintf('%s for %s expires on %s. Provide an updated certificate to avoid suspension.', $certificate, $supplier, $expiresAt);
        }

        $notifications->send(
            $recipients,
            'certificate_expiry',
            $title,
            $body,
            SupplierDocument::class,
            $payload['document_id'],
            [
                'supplier_id' => $payload['supplier_id'],
                'company_id' => $payload['company_id'],
                'type' => $payload['type'],
                'status' => $state,
                'expires_at' => $payload['expires_at'],
                'requires_reverification' => $requiresReverification,
            ]
        );
    }

    /**
     * @param  list<array{type?: string|null, expires_at?: string|null}>  $documents
     */
    private function buildReverificationNote(array $documents): string
    {
        $summary = collect($documents)
            ->map(function (array $payload): string {
                $type = strtoupper((string) ($payload['type'] ?? 'document'));
                $expiresAt = $payload['expires_at'] ?? 'unknown date';

                return sprintf('%s (%s)', $type, $expiresAt);
            })
            ->take(5)
            ->implode(', ');

        return $summary === ''
            ? 'Auto re-verification triggered by certificate expiry.'
            : sprintf('Auto re-verification triggered: %s expired.', $summary);
    }

    /**
     * @param list<array{document_id?: int|string|null}> $documents
     * @return array<int>
     */
    private function extractDocumentIds(array $documents): array
    {
        return collect($documents)
            ->pluck('document_id')
            ->filter(static fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
