<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class CompanyProfile extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'legal_name',
        'display_name',
        'tax_id',
        'registration_number',
        'emails',
        'phones',
        'bill_to',
        'ship_from',
        'logo_url',
        'mark_url',
    ];

    protected $casts = [
        'emails' => 'array',
        'phones' => 'array',
        'bill_to' => 'array',
        'ship_from' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getLogoUrlAttribute(?string $value): ?string
    {
        return $this->resolveMediaUrl($value);
    }

    public function getMarkUrlAttribute(?string $value): ?string
    {
        return $this->resolveMediaUrl($value);
    }

    private function resolveMediaUrl(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        return Storage::disk('public')->url($value);
    }
}
