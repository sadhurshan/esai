<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyLocaleSetting extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'locale',
        'timezone',
        'number_format',
        'date_format',
        'first_day_of_week',
        'weekend_days',
        'currency_primary',
        'currency_display_fx',
        'uom_base',
        'uom_maps',
    ];

    protected $casts = [
        'first_day_of_week' => 'integer',
        'weekend_days' => 'array',
        'currency_display_fx' => 'boolean',
        'uom_maps' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
