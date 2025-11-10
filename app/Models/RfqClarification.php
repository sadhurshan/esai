<?php

namespace App\Models;

use App\Enums\RfqClarificationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RfqClarification extends Model
{
    /** @use HasFactory<\Database\Factories\RfqClarificationFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'rfq_id',
        'user_id',
        'type',
        'message',
        'attachments_json',
        'version_increment',
        'version_no',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'type' => RfqClarificationType::class,
        'attachments_json' => 'array',
        'version_increment' => 'boolean',
        'version_no' => 'integer',
    ];

    protected $attributes = [
        'attachments_json' => '[]',
        'version_increment' => false,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(RFQ::class, 'rfq_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return list<int>
     */
    public function attachmentIds(): array
    {
        $attachments = $this->attachments_json;

        if (! is_array($attachments)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $value): ?int => is_numeric($value) ? (int) $value : null,
            $attachments
        )));
    }

    public function isAmendment(): bool
    {
        return $this->type === RfqClarificationType::Amendment;
    }
}
