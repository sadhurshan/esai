<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyAiSetting extends CompanyScopedModel
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'llm_answers_enabled',
        'llm_provider',
    ];

    protected $casts = [
        'llm_answers_enabled' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function resolvedProvider(): string
    {
        $provider = strtolower((string) $this->llm_provider);

        return $this->llm_answers_enabled && $provider === 'openai'
            ? 'openai'
            : 'dummy';
    }
}
