<?php

namespace App\Models\Concerns;

use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(self::companyScopeName(), static function (Builder $builder): void {
            $companyId = self::resolveScopedCompanyId();

            if ($companyId === null) {
                return;
            }

            $builder->where(
                $builder->qualifyColumn(self::companyForeignKey()),
                $companyId
            );
        });

        static::creating(static function (Model $model): void {
            $companyColumn = self::companyForeignKey();

            if ($model->getAttribute($companyColumn) !== null) {
                return;
            }

            $companyId = self::resolveScopedCompanyId();

            if ($companyId !== null) {
                $model->setAttribute($companyColumn, $companyId);
            }
        });
    }

    public function scopeForCompany(Builder $builder, int $companyId): Builder
    {
        return $builder
            ->withoutGlobalScope(self::companyScopeName())
            ->where($builder->qualifyColumn(self::companyForeignKey()), $companyId);
    }

    protected static function resolveScopedCompanyId(): ?int
    {
        if (CompanyContext::isBypassed()) {
            return null;
        }

        return CompanyContext::get();
    }

    protected static function companyForeignKey(): string
    {
        return 'company_id';
    }

    protected static function companyScopeName(): string
    {
        return 'company_scope';
    }
}
