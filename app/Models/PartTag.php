<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PartTag extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'part_id',
        'tag',
        'normalized_tag',
    ];

    protected static function booted(): void
    {
        static::saving(function (PartTag $partTag): void {
            $cleanTag = trim((string) $partTag->tag);

            if ($cleanTag === '') {
                throw new \InvalidArgumentException('Tag cannot be empty.');
            }

            $partTag->tag = $cleanTag;
            $partTag->normalized_tag = Str::lower($cleanTag);
        });
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }
}
