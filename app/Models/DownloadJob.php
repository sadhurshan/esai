<?php

namespace App\Models;

use App\Enums\DownloadDocumentType;
use App\Enums\DownloadFormat;
use App\Enums\DownloadJobStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DownloadJob extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'requested_by',
        'document_type',
        'document_id',
        'reference',
        'format',
        'status',
        'storage_disk',
        'file_path',
        'filename',
        'ready_at',
        'expires_at',
        'error_message',
        'meta',
        'attempts',
        'last_attempted_at',
    ];

    protected $casts = [
        'document_type' => DownloadDocumentType::class,
        'format' => DownloadFormat::class,
        'status' => DownloadJobStatus::class,
        'ready_at' => 'datetime',
        'expires_at' => 'datetime',
        'meta' => 'array',
        'last_attempted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isReady(): bool
    {
        return $this->status === DownloadJobStatus::Ready
            && ($this->expires_at === null || $this->expires_at->isFuture())
            && $this->file_path !== null;
    }
}
