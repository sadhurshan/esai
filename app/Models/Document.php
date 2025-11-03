<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Document extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'documentable_type',
        'documentable_id',
        'kind',
        'path',
        'filename',
        'mime',
        'size_bytes',
        'hash_sha256',
        'version',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }
}
