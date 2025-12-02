<?php

namespace App\Models;

use App\Enums\DigitalTwinAuditEvent as DigitalTwinAuditEventEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DigitalTwinAuditEvent extends Model
{
    /** @use HasFactory<\Database\Factories\DigitalTwinAuditEventFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'digital_twin_audit_events';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'digital_twin_id',
        'actor_id',
        'event',
        'meta',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'event' => DigitalTwinAuditEventEnum::class,
        'meta' => 'array',
    ];

    public function digitalTwin(): BelongsTo
    {
        return $this->belongsTo(DigitalTwin::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
