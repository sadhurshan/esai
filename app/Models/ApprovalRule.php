<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class ApprovalRule extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'target_type',
        'threshold_min',
        'threshold_max',
        'levels_json',
        'active',
    ];

    protected $casts = [
        'threshold_min' => 'decimal:2',
        'threshold_max' => 'decimal:2',
        'levels_json' => 'array',
        'active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function orderedLevels(): array
    {
        return $this->levelsCollection()->all();
    }

    public function levelsCount(): int
    {
        return $this->levelsCollection()->count();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function levelConfig(int $levelNo): ?array
    {
        return $this->levelsCollection()
            ->first(static fn (array $level) => (int) ($level['level_no'] ?? 0) === $levelNo);
    }

    protected function levelsCollection(): Collection
    {
        $levels = collect($this->levels_json ?? [])
            ->filter(static fn ($level) => is_array($level))
            ->map(static function (array $level): array {
                $level['level_no'] = (int) ($level['level_no'] ?? 0);
                $level['approver_role'] = $level['approver_role'] ?? null;
                $level['approver_user_id'] = isset($level['approver_user_id']) ? (int) $level['approver_user_id'] : null;
                $level['max_amount'] = isset($level['max_amount']) ? (float) $level['max_amount'] : null;

                return $level;
            })
            ->sortBy('level_no')
            ->values();

        return $levels;
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
