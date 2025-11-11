<?php

namespace App\Models;

use App\Enums\PlatformAdminRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAdmin extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'role',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'role' => PlatformAdminRole::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
