<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenantContext = app(TenantContext::class);

        if (! $user) {
            return $next($request);
        }

        $organizationId = (int) ($request->header('X-Organization-Id') ?: $user->organization_id ?: 0);
        if ($organizationId > 0) {
            if ((int) $user->organization_id !== $organizationId && ! $user->is_platform_user) {
                abort(403, 'Unauthorized organization context.');
            }

            $tenantContext->setOrganizationId($organizationId);
        }

        $branchId = (int) ($request->header('X-Branch-Id') ?: 0);
        if ($branchId > 0) {
            $branch = Branch::withoutGlobalScopes()->find($branchId);
            if (! $branch || (int) $branch->organization_id !== $tenantContext->organizationId()) {
                abort(403, 'Invalid branch context.');
            }

            if (! $user->hasBranchAccess($branchId) && ! $user->is_platform_user) {
                abort(403, 'No access to this branch.');
            }

            $tenantContext->setBranchId($branchId);
        }

        return $next($request);
    }
}

