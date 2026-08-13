<?php

use App\Models\User;
use App\Modules\TenantRegion\Models\Region;
use App\Modules\TenantRegion\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function siapkanTenantDenganAksesRegion(): array
{
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    aktifkanTenantContext($tenant->id);
    $region = Region::create(['name' => 'Banten', 'code' => 'ID-BT', 'level' => 'province']);
    $tenant->regions()->attach($region->id, ['id' => \Illuminate\Support\Str::uuid7(), 'access_level' => 'full']);

    return [$tenant, $region];
}

function loginSebagai(string $roleName, string $tenantId): User
{
    Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web', 'tenant_id' => $tenantId]);
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    setPermissionsTeamId($tenantId);
    $user->assignRole($roleName);

    return $user;
}

test('RESEARCHER bisa bikin entity baru, otomatis berstatus draft', function () {
    [$tenant, $region] = siapkanTenantDenganAksesRegion();
    loginSebagai('RESEARCHER', $tenant->id);

    $response = $this->postJson('/api/entities', [
        'name' => 'Budi Santoso',
        'type' => 'person',
        'region_id' => $region->id,
    ], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(201);
    $response->assertJsonPath('status', 'draft');
    $response->assertJsonPath('name', 'Budi Santoso');
});

test('LEGAL_REVIEWER TIDAK bisa bikin entity (separation of duties)', function () {
    [$tenant, $region] = siapkanTenantDenganAksesRegion();
    loginSebagai('LEGAL_REVIEWER', $tenant->id);

    $response = $this->postJson('/api/entities', [
        'name' => 'Budi Santoso',
        'type' => 'person',
        'region_id' => $region->id,
    ], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(403);
});

test('user tanpa role apapun TIDAK bisa bikin entity', function () {
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/entities', [
        'name' => 'Budi Santoso',
        'type' => 'person',
        'region_id' => (string) \Illuminate\Support\Str::uuid(),
    ], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(403);
});

test('mencoba kirim status lewat request diabaikan, tetap jadi draft', function () {
    [$tenant, $region] = siapkanTenantDenganAksesRegion();
    loginSebagai('RESEARCHER', $tenant->id);

    $response = $this->postJson('/api/entities', [
        'name' => 'Budi Santoso',
        'type' => 'person',
        'region_id' => $region->id,
        'status' => 'published', // sengaja nyoba nembus gate legal
    ], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(201);
    $response->assertJsonPath('status', 'draft');
});

test('entity ditolak kalau tenant tidak punya akses ke region yang dipilih', function () {
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    aktifkanTenantContext($tenant->id);
    $regionTanpaAkses = Region::create(['name' => 'Jawa Barat', 'code' => 'ID-JB', 'level' => 'province']);
    loginSebagai('RESEARCHER', $tenant->id);

    $response = $this->postJson('/api/entities', [
        'name' => 'Budi Santoso',
        'type' => 'person',
        'region_id' => $regionTanpaAkses->id,
    ], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(422);
});

test('type yang tidak valid ditolak validasi', function () {
    [$tenant, $region] = siapkanTenantDenganAksesRegion();
    loginSebagai('RESEARCHER', $tenant->id);

    $response = $this->postJson('/api/entities', [
        'name' => 'Budi Santoso',
        'type' => 'alien',
        'region_id' => $region->id,
    ], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(422);
});