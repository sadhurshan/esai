<?php

namespace App\Actions\Company;

use App\Models\CompanyDocument;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class DeleteCompanyDocumentAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function execute(CompanyDocument $document): void
    {
        DB::transaction(function () use ($document): void {
            $before = $document->toArray();

            $document->delete();

            $this->auditLogger->deleted($document, $before);
        });
    }
}
