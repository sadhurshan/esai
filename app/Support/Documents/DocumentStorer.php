<?php

namespace App\Support\Documents;

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentStorer
{
    public function store(
        UploadedFile $file,
        string $kind,
        ?int $companyId,
        string $documentableType,
        int $documentableId,
        string $disk = 'public'
    ): Document {
        $path = $file->store('documents/'.Str::slug($kind), $disk);

        return Document::create([
            'company_id' => $companyId,
            'documentable_type' => $documentableType,
            'documentable_id' => $documentableId,
            'kind' => $kind,
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize() ?? 0,
            'hash_sha256' => hash_file('sha256', $file->getRealPath()),
            'version' => 1,
        ]);
    }
}
