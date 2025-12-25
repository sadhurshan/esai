<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiChatMemory extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ai_chat_memories';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'thread_id',
        'company_id',
        'last_message_id',
        'memory_json',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'memory_json' => 'array',
        'last_message_id' => 'integer',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(AiChatThread::class, 'thread_id');
    }
}
