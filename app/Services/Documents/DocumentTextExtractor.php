<?php

namespace App\Services\Documents;

use App\Models\Document;

interface DocumentTextExtractor
{
    /**
     * Attempt to extract textual content for the provided document.
     */
    public function extract(Document $document): ?string;
}
