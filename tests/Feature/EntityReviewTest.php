<?php

use App\Models\User;
use App\Modules\Entity\Models\Entity;
use App\Modules\TenantRegion\Models\Region;
use App\Modules\TenantRegion\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function siapkanEntityUntukReview(string $status = 'draft'): array
{
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    aktifkanTenantContext($tenant->id);
    $region = Region::create(['name' => 'Banten', 'code' => 'ID-BT', 'level' => 'province']);
    $entity = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'Budi Santoso', 'status' => $status]);

    return [$tenant, $entity];
}

function loginSebagaiRole(string $roleName, string $tenantId): User
{
    Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web', 'tenant_id' => $tenantId]);
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    setPermissionsTeamId($tenantId);
    $user->assignRole($roleName);

    return $user;
}

test('RESEARCHER bisa ajukan entity draft untuk direview', function () {
    [$tenant, $entity] = siapkanEntityUntukReview('draft');
    loginSebagaiRole('RESEARCHER', $tenant->id);

    $response = $this->patchJson("/api/entities/{$entity->id}/submit-for-review", [], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(200);
    $response->assertJsonPath('status', 'pending_review');
});

test('LEGAL_REVIEWER bisa publish entity yang statusnya pending_review', function () {
    [$tenant, $entity] = siapkanEntityUntukReview('pending_review');
    $reviewer = loginSebagaiRole('LEGAL_REVIEWER', $tenant->id);

    $response = $this->patchJson("/api/entities/{$entity->id}/publish", [], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(200);
    $response->assertJsonPath('status', 'published');
    $response->assertJsonPath('reviewed_by', $reviewer->id);
});

test('RESEARCHER TIDAK bisa publish entity (cuma LEGAL_REVIEWER yang boleh)', function () {
    [$tenant, $entity] = siapkanEntityUntukReview('pending_review');
    loginSebagaiRole('RESEARCHER', $tenant->id);

    $response = $this->patchJson("/api/entities/{$entity->id}/publish", [], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(403);
});

test('entity draft TIDAK BISA langsung di-publish, harus lewat pending_review dulu', function () {
    [$tenant, $entity] = siapkanEntityUntukReview('draft');
    loginSebagaiRole('LEGAL_REVIEWER', $tenant->id);

    $response = $this->patchJson("/api/entities/{$entity->id}/publish", [], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(422);
    expect($entity->refresh()->status)->toBe('draft');
});

test('LEGAL_REVIEWER bisa minta revisi entity yang statusnya pending_review', function () {
    [$tenant, $entity] = siapkanEntityUntukReview('pending_review');
    loginSebagaiRole('LEGAL_REVIEWER', $tenant->id);

    $response = $this->patchJson("/api/entities/{$entity->id}/request-revision", [], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(200);
    $response->assertJsonPath('status', 'needs_revision');
});

test('entity published bisa diminta revisi ulang (correction/dispute path)', function () {
    [$tenant, $entity] = siapkanEntityUntukReview('published');
    loginSebagaiRole('LEGAL_REVIEWER', $tenant->id);

    $response = $this->patchJson("/api/entities/{$entity->id}/request-revision", [], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(200);
    $response->assertJsonPath('status', 'needs_revision');
});

test('entity needs_revision bisa diajukan review ulang', function () {
    [$tenant, $entity] = siapkanEntityUntukReview('needs_revision');
    loginSebagaiRole('RESEARCHER', $tenant->id);

    $response = $this->patchJson("/api/entities/{$entity->id}/submit-for-review", [], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(200);
    $response->assertJsonPath('status', 'pending_review');
});