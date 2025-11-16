<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'permissions',
        'is_system',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_system' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
