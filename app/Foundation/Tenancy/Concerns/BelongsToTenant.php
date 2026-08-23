<?php

namespace App\Foundation\Tenancy\Concerns;

use App\Foundation\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('company', function (Builder $builder): void {
            $companyId = app(TenantContext::class)->companyId();

            if ($companyId === null) {
                $builder->whereRaw('false');

                return;
            }

            $builder->where($builder->qualifyColumn('company_id'), $companyId);
        });

        static::creating(function (Model $model): void {
            $companyId = app(TenantContext::class)->companyId();

            if ($companyId === null) {
                throw new LogicException('Tenant-owned records require an active Company context.');
            }

            $assignedCompanyId = $model->getAttribute('company_id');

            if ($assignedCompanyId !== null && $assignedCompanyId !== $companyId) {
                throw new LogicException('A tenant-owned record cannot be assigned to another Company.');
            }

            $model->setAttribute('company_id', $companyId);
        });
    }
}
