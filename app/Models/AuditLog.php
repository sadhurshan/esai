<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends CompanyScopedModel
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'persona_type',
        'persona_company_id',
        'acting_supplier_id',
        'entity_type',
        'entity_id',
        'action',
        'before',
        'after',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'persona_company_id' => 'integer',
        'acting_supplier_id' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
