<?php

namespace App\Models;

use App\Enums\DocumentNumberResetPolicy;
use App\Enums\DocumentNumberType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class CompanyDocumentNumbering extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'document_type',
        'prefix',
        'seq_len',
        'next',
        'reset',
        'last_reset_year',
    ];

    protected $casts = [
        'document_type' => DocumentNumberType::class,
        'reset' => DocumentNumberResetPolicy::class,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function computeSample(): string
    {
        $next = (int) ($this->next ?? 1);
        $sequenceLength = max(3, (int) ($this->seq_len ?? 3));
        $padded = str_pad((string) max(1, $next), $sequenceLength, '0', STR_PAD_LEFT);
        $prefix = $this->prefix ?? '';

        if ($this->reset === DocumentNumberResetPolicy::Yearly) {
            $year = (int) Carbon::now()->format('Y');
            return sprintf('%s%s%s', $prefix, $year, $padded);
        }

        return sprintf('%s%s', $prefix, $padded);
    }
}
