<?php

use App\Models\User;
use App\Modules\Entity\Models\Entity;
use App\Modules\Evidence\Models\Evidence;
use App\Modules\Evidence\Models\Source;
use App\Modules\Relationship\Models\Relationship;
use App\Modules\TenantRegion\Models\Region;
use App\Modules\TenantRegion\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function siapkanRelationshipUntukReview(string $status = 'draft'): array
{
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    aktifkanTenantContext($tenant->id);
    $region = Region::create(['name' => 'Banten', 'code' => 'ID-BT', 'level' => 'province']);

    $orang = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'Budi Santoso']);
    $perusahaan = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'company', 'name' => 'PT Sejahtera']);
    $source = Source::create(['tenant_id' => $tenant->id, 'name' => 'Akta Perusahaan', 'type' => 'company_record']);
    $evidence = Evidence::create(['source_id' => $source->id, 'tenant_id' => $tenant->id, 'excerpt' => 'Budi adalah direktur']);

    $relationship = Relationship::create([
        'source_entity_id' => $orang->id,
        'target_entity_id' => $perusahaan->id,
        'evidence_id' => $evidence->id,
        'type' => 'directorship',
        'status' => $status,
    ]);

    return [$tenant, $relationship];
}

function loginSebagaiRoleReview(string $roleName, string $tenantId): User
{
    Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web', 'tenant_id' => $tenantId]);
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    setPermissionsTeamId($tenantId);
    $user->assignRole($roleName);

    return $user;
}

test('RESEARCHER bisa ajukan relationship draft untuk direview', function () {
    [$tenant, $relationship] = siapkanRelationshipUntukReview('draft');
    loginSebagaiRoleReview('RESEARCHER', $tenant->id);

    $response = $this->patchJson("/api/relationships/{$relationship->id}/submit-for-review", [], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(200);
    $response->assertJsonPath('status', 'pending_review');
});

test('LEGAL_REVIEWER bisa publish relationship yang statusnya pending_review', function () {
    [$tenant, $relationship] = siapkanRelationshipUntukReview('pending_review');
    $reviewer = loginSebagaiRoleReview('LEGAL_REVIEWER', $tenant->id);

    $response = $this->patchJson("/api/relationships/{$relationship->id}/publish", [], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(200);
    $response->assertJsonPath('status', 'published');
    $response->assertJsonPath('reviewed_by', $reviewer->id);
});

test('RESEARCHER TIDAK bisa publish relationship (cuma LEGAL_REVIEWER yang boleh)', function () {
    [$tenant, $relationship] = siapkanRelationshipUntukReview('pending_review');
    loginSebagaiRoleReview('RESEARCHER', $tenant->id);

    $response = $this->patchJson("/api/relationships/{$relationship->id}/publish", [], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(403);
});

test('relationship draft TIDAK BISA langsung di-publish, harus lewat pending_review dulu', function () {
    [$tenant, $relationship] = siapkanRelationshipUntukReview('draft');
    loginSebagaiRoleReview('LEGAL_REVIEWER', $tenant->id);

    $response = $this->patchJson("/api/relationships/{$relationship->id}/publish", [], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(422);
    expect($relationship->refresh()->status)->toBe('draft');
});

test('LEGAL_REVIEWER bisa minta revisi relationship yang statusnya pending_review', function () {
    [$tenant, $relationship] = siapkanRelationshipUntukReview('pending_review');
    loginSebagaiRoleReview('LEGAL_REVIEWER', $tenant->id);

    $response = $this->patchJson("/api/relationships/{$relationship->id}/request-revision", [], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(200);
    $response->assertJsonPath('status', 'needs_revision');
});