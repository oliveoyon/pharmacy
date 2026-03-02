<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthAndAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_login_returns_token_and_me_endpoint_works(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Tenant One',
            'code' => 'tenant-one',
        ]);

        $user = User::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Auth User',
            'email' => 'auth@example.com',
            'password' => 'password',
        ]);
        $user->assignRole('org_owner');

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'auth@example.com',
            'password' => 'password',
            'device_name' => 'phpunit',
        ]);

        $loginResponse->assertOk()
            ->assertJsonStructure([
                'token_type',
                'access_token',
                'user' => ['id', 'email', 'roles', 'permissions'],
            ]);

        $token = $loginResponse->json('access_token');

        $meResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Organization-Id', (string) $organization->id)
            ->getJson('/api/v1/auth/me');

        $meResponse->assertOk()
            ->assertJson([
                'id' => $user->id,
                'email' => 'auth@example.com',
                'organization_id' => $organization->id,
            ]);
    }

    public function test_platform_onboarding_requires_platform_user_flag(): void
    {
        $user = User::query()->create([
            'name' => 'Not Platform',
            'email' => 'noplat@example.com',
            'password' => 'password',
            'is_platform_user' => false,
        ]);
        $user->assignRole('super_admin');

        Sanctum::actingAs($user, guard: 'sanctum');

        $response = $this->postJson('/api/v1/platform/onboarding/organizations', [
            'organization_name' => 'Blocked Org',
            'branch_name' => 'Main',
            'branch_code' => 'MAIN',
            'admin_name' => 'Admin',
            'admin_email' => 'blocked-admin@example.com',
            'admin_password' => 'password123',
        ]);

        $response->assertForbidden();
    }

    public function test_tenant_route_requires_permission_even_with_branch_access(): void
    {
        [$organization, $branch] = $this->makeOrganizationWithBranch('tenant-two', 'Branch Two', 'B2');

        $user = User::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Limited User',
            'email' => 'limited@example.com',
            'password' => 'password',
            'is_organization_admin' => false,
        ]);
        $user->branches()->attach($branch->id, ['access_type' => 'assigned']);
        $user->assignRole('viewer');

        Sanctum::actingAs($user, guard: 'sanctum');

        $response = $this->withHeaders([
            'X-Organization-Id' => (string) $organization->id,
            'X-Branch-Id' => (string) $branch->id,
        ])->getJson('/api/v1/tenant/units');

        $response->assertForbidden();
    }

    public function test_user_cannot_access_another_organization_context(): void
    {
        [$orgA, $branchA] = $this->makeOrganizationWithBranch('tenant-a', 'Branch A', 'BA');
        [$orgB, $branchB] = $this->makeOrganizationWithBranch('tenant-b', 'Branch B', 'BB');

        $user = User::query()->create([
            'organization_id' => $orgA->id,
            'name' => 'Org A Owner',
            'email' => 'orga@example.com',
            'password' => 'password',
            'is_organization_admin' => true,
        ]);
        $user->assignRole('org_owner');

        Sanctum::actingAs($user, guard: 'sanctum');

        $response = $this->withHeaders([
            'X-Organization-Id' => (string) $orgB->id,
            'X-Branch-Id' => (string) $branchB->id,
        ])->getJson('/api/v1/tenant/units');

        $response->assertForbidden();
        $this->assertNotEquals($branchA->id, $branchB->id);
    }

    /**
     * @return array{Organization, Branch}
     */
    private function makeOrganizationWithBranch(string $code, string $branchName, string $branchCode): array
    {
        $organization = Organization::query()->create([
            'name' => strtoupper($code),
            'code' => $code,
        ]);

        $branch = Branch::withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'name' => $branchName,
            'code' => $branchCode,
            'type' => 'pharmacy',
        ]);

        return [$organization, $branch];
    }
}

