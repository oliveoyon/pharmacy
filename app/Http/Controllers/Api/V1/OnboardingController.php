<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class OnboardingController extends Controller
{
    public function storeOrganization(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'organization_code' => ['nullable', 'string', 'max:100', 'unique:organizations,code'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', 'string', 'max:10'],
            'branch_name' => ['required', 'string', 'max:255'],
            'branch_code' => ['required', 'string', 'max:100'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:8'],
        ]);

        $organization = DB::transaction(function () use ($validated) {
            $organization = Organization::query()->create([
                'name' => $validated['organization_name'],
                'code' => $validated['organization_code'] ?? Str::slug($validated['organization_name']).'-'.Str::lower(Str::random(5)),
                'timezone' => $validated['timezone'] ?? 'UTC',
                'currency' => $validated['currency'] ?? 'USD',
            ]);

            $branch = Branch::query()->create([
                'organization_id' => $organization->id,
                'name' => $validated['branch_name'],
                'code' => $validated['branch_code'],
                'type' => 'pharmacy',
            ]);

            $admin = User::query()->create([
                'organization_id' => $organization->id,
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'password' => $validated['admin_password'],
                'is_organization_admin' => true,
            ]);

            $organization->users()->attach($admin->id, ['status' => 'active']);
            $admin->branches()->attach($branch->id, ['access_type' => 'manager']);
            $admin->assignRole(Role::findOrCreate('org_owner', 'web'));

            $organization->setRelation('branches', collect([$branch]));
            $organization->setRelation('users', collect([$admin]));

            return $organization;
        });

        return response()->json([
            'message' => 'Organization onboarded successfully.',
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'code' => $organization->code,
            ],
            'initial_branch' => [
                'id' => $organization->branches->first()->id,
                'name' => $organization->branches->first()->name,
                'code' => $organization->branches->first()->code,
            ],
            'admin_user' => [
                'id' => $organization->users->first()->id,
                'name' => $organization->users->first()->name,
                'email' => $organization->users->first()->email,
            ],
        ], 201);
    }

    public function storeBranch(Request $request, Organization $organization): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100'],
            'type' => ['nullable', 'in:pharmacy,warehouse'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'manager_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $existingCode = Branch::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('code', $validated['code'])
            ->exists();

        if ($existingCode) {
            return response()->json([
                'message' => 'Branch code already exists in this organization.',
            ], 422);
        }

        $branch = Branch::withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'name' => $validated['name'],
            'code' => $validated['code'],
            'type' => $validated['type'] ?? 'pharmacy',
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
        ]);

        if (! empty($validated['manager_user_id'])) {
            $user = User::query()->findOrFail($validated['manager_user_id']);
            if ((int) $user->organization_id !== (int) $organization->id) {
                return response()->json([
                    'message' => 'Manager user does not belong to this organization.',
                ], 422);
            }

            $user->branches()->syncWithoutDetaching([$branch->id => ['access_type' => 'manager']]);
        }

        return response()->json([
            'message' => 'Branch created successfully.',
            'branch' => $branch,
        ], 201);
    }
}

