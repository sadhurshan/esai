<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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
}
