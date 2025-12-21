<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiChatMessage extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';
    public const ROLE_SYSTEM = 'system';
    public const ROLE_TOOL = 'tool';

    /**
     * @var list<string>
     */
    public const ROLES = [
        self::ROLE_USER,
        self::ROLE_ASSISTANT,
        self::ROLE_SYSTEM,
        self::ROLE_TOOL,
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    /**
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
    ];

    protected $table = 'ai_chat_messages';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'thread_id',
        'company_id',
        'user_id',
        'role',
        'content_text',
        'content_json',
        'citations_json',
        'tool_calls_json',
        'tool_results_json',
        'latency_ms',
        'status',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'content_json' => 'array',
        'citations_json' => 'array',
        'tool_calls_json' => 'array',
        'tool_results_json' => 'array',
        'latency_ms' => 'integer',
    ];

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(AiChatThread::class, 'thread_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
