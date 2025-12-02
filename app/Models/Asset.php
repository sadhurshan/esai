<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'system_id',
        'location_id',
        'name',
        'tag',
        'serial_no',
        'model_no',
        'manufacturer',
        'commissioned_at',
        'status',
        'meta',
    ];

    protected $casts = [
        'commissioned_at' => 'date',
        'meta' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function system(): BelongsTo
    {
        return $this->belongsTo(System::class);
    }

    public function bomItems(): HasMany
    {
        return $this->hasMany(AssetBomItem::class);
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'asset_documents');
    }

    public function maintenanceProcedures(): BelongsToMany
    {
        return $this->belongsToMany(MaintenanceProcedure::class, 'asset_procedure_links')
            ->withPivot(['frequency_value', 'frequency_unit', 'last_done_at', 'next_due_at', 'meta'])
            ->withTimestamps();
    }

    public function procedureLinks(): HasMany
    {
        return $this->hasMany(AssetProcedureLink::class);
    }
}
