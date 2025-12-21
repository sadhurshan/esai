<?php

namespace App\Models;

use App\Enums\SupplierScrapeJobStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierScrapeJob extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'user_id',
        'query',
        'region',
        'status',
        'parameters_json',
        'result_count',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'parameters_json' => 'array',
        'status' => SupplierScrapeJobStatus::class,
        'result_count' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scrapedSuppliers(): HasMany
    {
        return $this->hasMany(ScrapedSupplier::class, 'scrape_job_id');
    }
}
