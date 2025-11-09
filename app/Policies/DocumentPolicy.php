<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    /**
     * @param array{company_id?: int, documentable?: object|null}|array<string, mixed> $context
     */
    public function create(User $user, array $context): bool
    {
        $companyId = isset($context['company_id']) ? (int) $context['company_id'] : null;

        if ($companyId === null) {
            return false;
        }

        return (int) ($user->company_id ?? 0) === $companyId;
    }

    public function view(User $user, Document $document): bool
    {
        if ($document->visibility === 'public') {
            return true;
        }

        $userCompany = (int) ($user->company_id ?? 0);
        $documentCompany = (int) ($document->company_id ?? 0);

        if ($document->visibility === 'company') {
            return $userCompany === $documentCompany;
        }

        return $userCompany === $documentCompany;
    }

    public function delete(User $user, Document $document): bool
    {
        return (int) ($user->company_id ?? 0) === (int) ($document->company_id ?? 0);
    }
}
