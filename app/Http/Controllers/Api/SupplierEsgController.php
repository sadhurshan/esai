<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentCategory;
use App\Enums\DocumentKind;
use App\Enums\EsgCategory;
use App\Http\Requests\Supplier\StoreEsgRecordRequest;
use App\Http\Requests\Supplier\UpdateEsgRecordRequest;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\SupplierEsgRecordResource;
use App\Jobs\SendEsgReminderNotification;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierEsgRecord;
use App\Models\User;
use App\Services\EsgExportService;
use App\Support\Audit\AuditLogger;
use App\Support\Documents\DocumentStorer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SupplierEsgController extends ApiController
{
    public function __construct(
        private readonly DocumentStorer $documentStorer,
        private readonly AuditLogger $auditLogger,
        private readonly EsgExportService $exportService,
    ) {
    }

    public function index(Request $request, Supplier $supplier): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        if (! $this->canAccessSupplier($user, $supplier)) {
            return $this->fail('Supplier not accessible.', 403);
        }

        $query = SupplierEsgRecord::query()
            ->with('document')
            ->where('supplier_id', $supplier->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $category = $request->query('category');
        if ($category !== null) {
            $categoryEnum = EsgCategory::tryFrom((string) $category);
            if ($categoryEnum === null) {
                return $this->fail('Invalid category filter.', 422, [
                    'category' => ['Category must be one of: '.implode(', ', EsgCategory::values()).'.'],
                ]);
            }

            $query->where('category', $categoryEnum->value);
        }

        $expiry = $request->query('expiry');
        if ($expiry !== null) {
            $expiryValue = strtolower((string) $expiry);
            if (! in_array($expiryValue, ['active', 'expired', 'all'], true)) {
                return $this->fail('Invalid expiry filter.', 422, [
                    'expiry' => ['Expiry filter must be active, expired, or all.'],
                ]);
            }

            if ($expiryValue !== 'all') {
                $query->whereNotNull('expires_at')
                    ->where('expires_at', $expiryValue === 'expired' ? '<=' : '>=', now());
            }
        }

        $paginator = $query->cursorPaginate($this->perPage($request, 15, 75));

        $this->queueReminders(collect($paginator->items()));

        ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, SupplierEsgRecordResource::class);

        return $this->ok(
            $items,
            'Supplier ESG records retrieved.',
            ['envelope' => $meta['envelope'] ?? []]
        );
    }

    public function store(StoreEsgRecordRequest $request, Supplier $supplier): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        if (! $this->canAccessSupplier($user, $supplier)) {
            return $this->fail('Supplier not accessible.', 403);
        }

        if (! $this->canManageEsg($user)) {
            return $this->fail('Forbidden.', 403);
        }

        $validated = $request->validated();
        $category = EsgCategory::from($validated['category']);

        $document = null;

        /** @var UploadedFile|null $file */
        $file = $request->file('file');

        if ($file instanceof UploadedFile) {
            $kind = match ($category) {
                EsgCategory::Policy => DocumentKind::Manual->value,
                EsgCategory::Certificate => DocumentKind::Certificate->value,
                default => DocumentKind::Supplier->value,
            };

            $document = $this->documentStorer->store(
                $user,
                $file,
                DocumentCategory::Esg->value,
                $supplier->company_id,
                $supplier->getMorphClass(),
                (int) $supplier->getKey(),
                [
                    'kind' => $kind,
                    'visibility' => 'company',
                    'expires_at' => $validated['expires_at'] ?? null,
                ]
            );
        }

        $record = null;

        DB::transaction(function () use ($supplier, $validated, $document, &$record): void {
            $payload = [
                'company_id' => $supplier->company_id,
                'supplier_id' => $supplier->id,
                'category' => $validated['category'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'document_id' => $document?->id,
                'data_json' => $validated['data_json'] ?? null,
                'approved_at' => $validated['approved_at'] ?? null,
                'expires_at' => $validated['expires_at'] ?? null,
            ];

            $record = SupplierEsgRecord::create($payload);

            $this->auditLogger->created($record, Arr::only($record->toArray(), array_keys($payload)));
        });

        return $this->ok(
            (new SupplierEsgRecordResource($record->fresh('document')))->toArray($request),
            'ESG record created.'
        )->setStatusCode(201);
    }

    public function update(UpdateEsgRecordRequest $request, Supplier $supplier, SupplierEsgRecord $record): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        if (! $this->canAccessSupplier($user, $supplier) || (int) $record->supplier_id !== (int) $supplier->id) {
            return $this->fail('Supplier not accessible.', 403);
        }

        if (! $this->canManageEsg($user)) {
            return $this->fail('Forbidden.', 403);
        }

        if ($record->isExpired()) {
            return $this->fail('Expired ESG records cannot be updated.', 422);
        }

        $validated = $request->validated();

        if (empty($validated)) {
            return $this->ok((new SupplierEsgRecordResource($record->fresh('document')))->toArray($request), 'No changes applied.');
        }

        $before = Arr::only($record->toArray(), array_keys($validated));

        $record->fill($validated);
        $record->save();

        $fresh = $record->fresh('document');
        $this->auditLogger->updated($record, $before, Arr::only($fresh->toArray(), array_keys($validated)));

        return $this->ok((new SupplierEsgRecordResource($fresh))->toArray($request), 'ESG record updated.');
    }

    public function destroy(Request $request, Supplier $supplier, SupplierEsgRecord $record): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        if (! $this->canAccessSupplier($user, $supplier) || (int) $record->supplier_id !== (int) $supplier->id) {
            return $this->fail('Supplier not accessible.', 403);
        }

        if (! $this->canManageEsg($user)) {
            return $this->fail('Forbidden.', 403);
        }

        $before = $record->toArray();
        $record->delete();

        $this->auditLogger->deleted($record, $before);

        return $this->ok(null, 'ESG record removed.');
    }

    public function export(Request $request, Supplier $supplier): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        if (! $this->canAccessSupplier($user, $supplier)) {
            return $this->fail('Supplier not accessible.', 403);
        }

        if (! $this->canManageEsg($user)) {
            return $this->fail('Forbidden.', 403);
        }

    $from = $this->parseDate($request->input('from'), now()->subMonths(12), false);
    $to = $this->parseDate($request->input('to'), now(), true);

        if ($from->greaterThan($to)) {
            return $this->fail('The export start date must be before or equal to the end date.', 422, [
                'from' => ['Start date must be before end date.'],
            ]);
        }

        $document = $this->exportService->export($user, $supplier, $from, $to);

        return $this->ok(
            (new DocumentResource($document))->toArray($request),
            'Scope-3 support pack generated.'
        );
    }

    private function canAccessSupplier(User $user, Supplier $supplier): bool
    {
        $user->loadMissing('company');
        $company = $user->company;

        if (! $company instanceof Company) {
            return false;
        }

        return (int) $company->id === (int) $supplier->company_id;
    }

    private function canManageEsg(User $user): bool
    {
        return in_array($user->role, ['buyer_admin', 'supplier_admin', 'platform_super'], true)
            || $user->company?->owner_user_id === $user->id;
    }

    private function parseDate(mixed $input, Carbon $default, bool $endOfDay): Carbon
    {
        if ($input === null || $input === '') {
            $date = $default->copy();
        }
        else {
            $date = Carbon::parse($input);
        }

        return $endOfDay ? $date->copy()->endOfDay() : $date->copy()->startOfDay();
    }

    private function queueReminders(Collection $records): void
    {
        $records->each(function (SupplierEsgRecord $record): void {
            if ($record->category === EsgCategory::Certificate && $record->expires_at !== null && $record->expires_at->isPast()) {
                SendEsgReminderNotification::dispatch($record->id, 'expired_certificate');
            }

            $data = $record->data_json ?? [];
            if ($record->category === EsgCategory::Emission && empty($data)) {
                SendEsgReminderNotification::dispatch($record->id, 'missing_emission_data');
            }
        });
    }
}
