<?php

namespace App\Actions\Company;

use App\Enums\DocumentCategory;
use App\Enums\DocumentKind;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Documents\DocumentStorer;
use App\Support\Security\VirusScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class StoreCompanyDocumentAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DocumentStorer $documentStorer,
        private readonly VirusScanner $virusScanner,
    ) {}

    public function execute(Company $company, User $user, string $type, UploadedFile $file): CompanyDocument
    {
        $this->virusScanner->assertClean($file, [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'document_type' => $type,
        ]);

        return DB::transaction(function () use ($company, $user, $type, $file): CompanyDocument {
            $document = $this->documentStorer->store(
                $user,
                $file,
                $this->categoryFor($type),
                $company->id,
                $company->getMorphClass(),
                (int) $company->getKey(),
                [
                    'kind' => DocumentKind::Supplier->value,
                    'visibility' => 'company',
                    'meta' => [
                        'context' => 'company_document',
                        'company_document_type' => $type,
                    ],
                ]
            );

            $companyDocument = CompanyDocument::create([
                'company_id' => $company->id,
                'document_id' => $document->id,
                'type' => $type,
                'path' => $document->path,
            ]);

            $companyDocument->setRelation('document', $document);

            $this->auditLogger->created($companyDocument, [
                'company_id' => $company->id,
                'document_id' => $document->id,
                'type' => $type,
            ]);

            return $companyDocument;
        });
    }

    private function categoryFor(string $type): string
    {
        return match ($type) {
            'registration', 'tax' => DocumentCategory::Financial->value,
            'esg' => DocumentCategory::Esg->value,
            default => DocumentCategory::Other->value,
        };
    }
}
