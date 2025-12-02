<?php

namespace App\Models;

use App\Enums\ExportRequestStatus;
use App\Enums\ExportRequestType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExportRequest extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'requested_by',
        'type',
        'status',
        'filters',
        'file_path',
        'expires_at',
        'completed_at',
        'error_message',
    ];

    protected $casts = [
        'filters' => 'array',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'type' => ExportRequestType::class,
        'status' => ExportRequestStatus::class,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isDownloadable(): bool
    {
        if ($this->status !== ExportRequestStatus::Completed) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return $this->file_path !== null;
    }
}
