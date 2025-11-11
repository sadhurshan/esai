<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailTemplate extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'key',
        'name',
        'subject',
        'body_html',
        'body_text',
        'enabled',
        'meta',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'meta' => 'array',
    ];
}
