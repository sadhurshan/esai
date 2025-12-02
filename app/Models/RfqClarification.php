<?php

namespace App\Models;

use App\Enums\RfqClarificationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RfqClarification extends CompanyScopedModel
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

        $ids = [];

        foreach ($attachments as $attachment) {
            if (is_array($attachment) && isset($attachment['document_id']) && is_numeric($attachment['document_id'])) {
                $ids[] = (int) $attachment['document_id'];

                continue;
            }

            if (is_numeric($attachment)) {
                $ids[] = (int) $attachment;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function attachmentMetadata(): array
    {
        $attachments = $this->attachments_json;

        if (! is_array($attachments)) {
            return [];
        }

        $normalized = [];

        foreach ($attachments as $attachment) {
            if (is_array($attachment) && isset($attachment['document_id']) && is_numeric($attachment['document_id'])) {
                $attachment['document_id'] = (int) $attachment['document_id'];
                $normalized[] = $attachment;

                continue;
            }

            if (is_numeric($attachment)) {
                $normalized[] = ['document_id' => (int) $attachment];
            }
        }

        return $normalized;
    }

    public function isAmendment(): bool
    {
        return $this->type === RfqClarificationType::Amendment;
    }
}
