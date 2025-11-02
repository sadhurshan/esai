<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $number
 * @property string $item_name
 * @property string $type
 * @property int $quantity
 * @property string $material
 * @property string $method
 * @property string|null $tolerance
 * @property string|null $finish
 * @property string $client_company
 * @property string $status
 * @property bool $is_open_bidding
 * @property string|null $notes
 * @property string|null $cad_path
 * @property \Illuminate\Support\Carbon|null $deadline_at
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Collection<int, \App\Models\RFQQuote> $quotes
 */
class RFQ extends Model
{
    /** @use HasFactory<\Database\Factories\RFQFactory> */
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'rfqs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'number',
        'item_name',
        'type',
        'quantity',
        'material',
        'method',
        'tolerance',
        'finish',
        'client_company',
        'status',
        'deadline_at',
        'sent_at',
        'is_open_bidding',
        'notes',
        'cad_path',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'integer',
        'deadline_at' => 'datetime',
        'sent_at' => 'datetime',
        'is_open_bidding' => 'boolean',
    ];

    public function quotes(): HasMany
    {
        return $this->hasMany(RFQQuote::class);
    }
}
