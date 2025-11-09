<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $table = 'user_notification_prefs';

    protected $fillable = [
        'user_id',
        'event_type',
        'channel',
        'digest',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
