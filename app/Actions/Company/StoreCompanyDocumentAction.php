<?php

namespace App\Actions\Company;

use App\Models\Company;
use App\Models\CompanyDocument;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class StoreCompanyDocumentAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function execute(Company $company, string $type, UploadedFile $file): CompanyDocument
    {
        return DB::transaction(function () use ($company, $type, $file): CompanyDocument {
            $disk = config('filesystems.default', 'local');
            $path = $file->store('company-documents/'.$company->id, $disk);

            $document = CompanyDocument::create([
                'company_id' => $company->id,
                'type' => $type,
                'path' => $path,
            ]);

            $this->auditLogger->created($document);

            return $document;
        });
    }
}
