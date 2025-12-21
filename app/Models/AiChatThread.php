<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiChatThread extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    /**
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_CLOSED,
    ];

    protected $table = 'ai_chat_threads';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'user_id',
        'title',
        'status',
        'last_message_at',
        'metadata_json',
        'thread_summary',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'metadata_json' => 'array',
        'last_message_at' => 'datetime',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_OPEN,
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiChatMessage::class, 'thread_id');
    }

    public function latestMessages(int $limit = 20): Collection
    {
        return $this->messages()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->sortBy('id')
            ->values();
    }

    public function appendMessage(string $role, array $attributes): AiChatMessage
    {
        $payload = array_merge($attributes, [
            'company_id' => $this->company_id,
            'role' => $role,
        ]);
        $message = $this->messages()->create($payload);

        $this->forceFill([
            'last_message_at' => now(),
        ])->save();

        return $message;
    }
}
