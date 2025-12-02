<?php

namespace App\Models;

use App\Enums\DigitalTwinStatus;
use App\Enums\DigitalTwinVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class DigitalTwin extends CompanyScopedModel
{
    /** @use HasFactory<\Database\Factories\DigitalTwinFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'digital_twins';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'category_id',
        'slug',
        'code',
        'title',
        'summary',
        'status',
        'version',
        'revision_notes',
        'tags',
        'tags_search',
        'thumbnail_path',
        'visibility',
        'published_at',
        'archived_at',
        'extra',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'tags' => 'array',
        'status' => DigitalTwinStatus::class,
        'visibility' => DigitalTwinVisibility::class,
        'published_at' => 'datetime',
        'archived_at' => 'datetime',
        'extra' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $twin): void {
            $tags = $twin->tags ?? [];
            if (is_array($tags) && ! empty($tags)) {
                $twin->tags_search = Str::lower(implode(' ', $tags));
            } else {
                $twin->tags_search = null;
            }

            if (! $twin->slug) {
                $twin->slug = Str::slug($twin->title.'-'.Str::random(6));
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DigitalTwinCategory::class, 'category_id');
    }

    public function specs(): HasMany
    {
        return $this->hasMany(DigitalTwinSpec::class)->orderBy('sort_order');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(DigitalTwinAsset::class)->orderByDesc('is_primary')->orderBy('id');
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(DigitalTwinAuditEvent::class)->latest();
    }

    public function scopePublished($query)
    {
        return $query->where('status', DigitalTwinStatus::Published);
    }
}
