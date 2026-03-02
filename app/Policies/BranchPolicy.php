<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function view(User $user, Branch $branch): bool
    {
        return $user->hasBranchAccess($branch->id);
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->is_platform_user
            || ($user->is_organization_admin && (int) $user->organization_id === (int) $branch->organization_id)
            || $user->can('branches.manage');
    }
}

