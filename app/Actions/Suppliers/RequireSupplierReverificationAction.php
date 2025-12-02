<?php

namespace App\Actions\Suppliers;

use App\Enums\CompanySupplierStatus;
use App\Enums\SupplierApplicationStatus;
use App\Models\Company;
use App\Models\SupplierApplication;
use App\Models\SupplierDocument;
use App\Models\User;
use App\Notifications\SupplierApplicationSubmitted;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class RequireSupplierReverificationAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * @param array{notes?: string|null, document_ids?: array<int, int|string>} $context
     */
    public function execute(Company $company, array $context = []): bool
    {
        return $this->db->transaction(function () use ($company, $context): bool {
            $locked = Company::query()->lockForUpdate()->find($company->id);

            if ($locked === null) {
                return false;
            }

            if ($locked->supplier_status !== CompanySupplierStatus::Approved) {
                return false;
            }

            $companyBefore = $locked->getOriginal();

            $locked->supplier_status = CompanySupplierStatus::Pending;
            if ($locked->directory_visibility !== 'private') {
                $locked->directory_visibility = 'private';
            }

            $companyChanges = $locked->getDirty();
            if ($companyChanges !== []) {
                $locked->save();
                $this->auditLogger->updated($locked, $companyBefore, $companyChanges);
            }

            $pendingExists = SupplierApplication::query()
                ->where('company_id', $locked->id)
                ->where('status', SupplierApplicationStatus::Pending)
                ->exists();

            if ($pendingExists) {
                return true;
            }

            $template = SupplierApplication::query()
                ->where('company_id', $locked->id)
                ->latest('created_at')
                ->first();

            $application = SupplierApplication::create([
                'company_id' => $locked->id,
                'submitted_by' => $locked->owner_user_id,
                'status' => SupplierApplicationStatus::Pending,
                'form_json' => $template?->form_json,
                'notes' => Str::limit($context['notes'] ?? 'Auto re-verification triggered by certificate expiry.', 240),
            ]);

            $this->auditLogger->created($application, Arr::only($application->toArray(), [
                'company_id',
                'status',
            ]));

            $documentIds = $this->normalizeDocumentIds((array) ($context['document_ids'] ?? []));

            if ($documentIds->isNotEmpty()) {
                $validDocumentIds = SupplierDocument::query()
                    ->where('company_id', $locked->id)
                    ->whereIn('id', $documentIds)
                    ->pluck('id')
                    ->all();

                if ($validDocumentIds !== []) {
                    $application->documents()->sync($validDocumentIds);
                }
            }

            $this->notifyPlatformAdmins($application);

            return true;
        });
    }

    /**
     * @param array<int, int|string> $ids
     */
    private function normalizeDocumentIds(array $ids): Collection
    {
        return collect($ids)
            ->filter(static fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values();
    }

    private function notifyPlatformAdmins(SupplierApplication $application): void
    {
        $admins = User::query()->where('role', 'platform_super')->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::send($admins, new SupplierApplicationSubmitted($application->fresh(['company', 'submittedBy'])));
    }
}
