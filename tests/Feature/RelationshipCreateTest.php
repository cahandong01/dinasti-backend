<?php

use App\Models\User;
use App\Modules\Entity\Models\Entity;
use App\Modules\Evidence\Models\Evidence;
use App\Modules\Evidence\Models\Source;
use App\Modules\TenantRegion\Models\Region;
use App\Modules\TenantRegion\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function siapkanDuaEntityDanEvidence(): array
{
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    aktifkanTenantContext($tenant->id);
    $region = Region::create(['name' => 'Banten', 'code' => 'ID-BT', 'level' => 'province']);

    $orang = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'Budi Santoso']);
    $perusahaan = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'company', 'name' => 'PT Sejahtera']);

    $source = Source::create(['tenant_id' => $tenant->id, 'name' => 'Akta Perusahaan', 'type' => 'company_record']);
    $evidence = Evidence::create(['source_id' => $source->id, 'tenant_id' => $tenant->id, 'excerpt' => 'Budi adalah direktur']);

    Role::firstOrCreate(['name' => 'RESEARCHER', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    setPermissionsTeamId($tenant->id);
    $user->assignRole('RESEARCHER');

    return [$tenant, $orang, $perusahaan, $evidence];
}

test('RESEARCHER bisa bikin relationship baru, otomatis berstatus draft', function () {
    [$tenant, $orang, $perusahaan, $evidence] = siapkanDuaEntityDanEvidence();

    $response = $this->postJson('/api/relationships', [
        'source_entity_id' => $orang->id,
        'target_entity_id' => $perusahaan->id,
        'evidence_id' => $evidence->id,
        'type' => 'directorship',
        'valid_from' => '2020-01-01',
    ], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(201);
    $response->assertJsonPath('status', 'draft');
    $response->assertJsonPath('type', 'directorship');
});

test('relationship tanpa evidence_id ditolak validasi', function () {
    [$tenant, $orang, $perusahaan] = siapkanDuaEntityDanEvidence();

    $response = $this->postJson('/api/relationships', [
        'source_entity_id' => $orang->id,
        'target_entity_id' => $perusahaan->id,
        'type' => 'directorship',
    ], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('evidence_id');
});

test('relationship ke diri sendiri (source sama dengan target) ditolak', function () {
    [$tenant, $orang, , $evidence] = siapkanDuaEntityDanEvidence();

    $response = $this->postJson('/api/relationships', [
        'source_entity_id' => $orang->id,
        'target_entity_id' => $orang->id,
        'evidence_id' => $evidence->id,
        'type' => 'directorship',
    ], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(422);
});

test('relationship ke entity milik tenant lain ditolak (RLS via validasi exists)', function () {
    [$tenant, $orang, , $evidence] = siapkanDuaEntityDanEvidence();

    // Bikin entity di tenant lain
    $tenantLain = Tenant::create(['name' => 'Tenant Lain', 'slug' => 'tenant-lain']);
    Role::firstOrCreate(['name' => 'SUPER_ADMIN', 'guard_name' => 'web', 'tenant_id' => null]);
    $adminLain = User::factory()->create();
    setPermissionsTeamId($tenantLain->id);
    $adminLain->assignRole('SUPER_ADMIN');
    aktifkanTenantContext($tenantLain->id);
    $regionLain = Region::create(['name' => 'Jawa Barat', 'code' => 'ID-JB', 'level' => 'province']);
    $entityTenantLain = Entity::create(['tenant_id' => $tenantLain->id, 'region_id' => $regionLain->id, 'type' => 'person', 'name' => 'Orang Tenant Lain']);

    // Balik ke context tenant A buat request-nya
    aktifkanTenantContext($tenant->id);

    $response = $this->postJson('/api/relationships', [
        'source_entity_id' => $orang->id,
        'target_entity_id' => $entityTenantLain->id,
        'evidence_id' => $evidence->id,
        'type' => 'directorship',
    ], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('target_entity_id');
});

test('PUBLIC_USER (tanpa role) tidak bisa bikin relationship', function () {
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/relationships', [
        'source_entity_id' => (string) \Illuminate\Support\Str::uuid(),
        'target_entity_id' => (string) \Illuminate\Support\Str::uuid(),
        'evidence_id' => (string) \Illuminate\Support\Str::uuid(),
        'type' => 'directorship',
    ], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(403);
});