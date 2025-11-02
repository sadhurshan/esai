<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $number
 * @property string $party_type
 * @property string $party_name
 * @property string $item_name
 * @property int $quantity
 * @property string $total_usd
 * @property string $status
 * @property \Illuminate\Support\Carbon $ordered_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'number',
        'party_type',
        'party_name',
        'item_name',
        'quantity',
        'total_usd',
        'ordered_at',
        'status',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'integer',
        'total_usd' => 'decimal:2',
        'ordered_at' => 'datetime',
    ];
}
