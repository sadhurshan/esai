<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'documentable_type',
        'documentable_id',
        'kind',
        'category',
        'visibility',
        'version_number',
        'expires_at',
        'path',
        'filename',
        'mime',
        'size_bytes',
        'hash',
        'watermark',
        'meta',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'expires_at' => 'datetime',
        'watermark' => 'array',
        'meta' => 'array',
    ];

    protected $attributes = [
        'watermark' => '[]',
        'meta' => '[]',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'documentable_id', 'documentable_id')
            ->where('documentable_type', $this->documentable_type)
            ->where('kind', $this->kind)
            ->where('category', $this->category)
            ->orderBy('version_number');
    }

    public function isExpired(): bool
    {
        if (! $this->expires_at instanceof Carbon) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }

    public function temporaryDownloadUrl(int $ttlMinutes = 15): ?string
    {
        $path = (string) ($this->path ?? '');

        if ($path === '') {
            return null;
        }

        $disk = config('documents.disk', config('filesystems.default', 'public'));
        $expiresAt = now()->addMinutes(max(1, $ttlMinutes));

        try {
            $storage = Storage::disk($disk);
        } catch (\Throwable) {
            return null;
        }

        if (method_exists($storage, 'temporaryUrl')) {
            try {
                return $storage->temporaryUrl($path, $expiresAt);
            } catch (\Throwable) {
                // Fall through to plain URLs.
            }
        }

        try {
            return $storage->url($path);
        } catch (\Throwable) {
            return null;
        }
    }
}
