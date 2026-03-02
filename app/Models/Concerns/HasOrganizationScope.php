<?php

namespace App\Models\Concerns;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;

trait HasOrganizationScope
{
    public static function bootHasOrganizationScope(): void
    {
        static::addGlobalScope('organization', function (Builder $builder): void {
            $tenantContext = app(TenantContext::class);
            $organizationId = $tenantContext->organizationId();

            if ($organizationId !== null) {
                $builder->where($builder->qualifyColumn('organization_id'), $organizationId);
            }
        });

        static::creating(function ($model): void {
            if (! isset($model->organization_id) || $model->organization_id === null) {
                $organizationId = app(TenantContext::class)->organizationId();

                if ($organizationId !== null) {
                    $model->organization_id = $organizationId;
                }
            }
        });
    }
}

