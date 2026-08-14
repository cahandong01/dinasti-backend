<?php

use App\Models\User;
use App\Modules\Entity\Models\Entity;
use App\Modules\TenantRegion\Models\Region;
use App\Modules\TenantRegion\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function siapkanEntityUntukUpdate(string $status = 'draft'): array
{
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    aktifkanTenantContext($tenant->id);
    $region = Region::create(['name' => 'Banten', 'code' => 'ID-BT', 'level' => 'province']);
    $entity = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'Budi Santoso', 'status' => $status]);

    Role::firstOrCreate(['name' => 'RESEARCHER', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    setPermissionsTeamId($tenant->id);
    $user->assignRole('RESEARCHER');

    return [$tenant, $entity, $region];
}

test('entity berstatus draft bisa diedit', function () {
    [$tenant, $entity] = siapkanEntityUntukUpdate('draft');

    $response = $this->patchJson("/api/entities/{$entity->id}", [
        'name' => 'Budi Santoso Wijaya',
    ], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(200);
    $response->assertJsonPath('name', 'Budi Santoso Wijaya');
});

test('entity berstatus needs_revision bisa diedit', function () {
    [$tenant, $entity] = siapkanEntityUntukUpdate('needs_revision');

    $response = $this->patchJson("/api/entities/{$entity->id}", [
        'name' => 'Nama Sudah Dikoreksi',
    ], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(200);
    $response->assertJsonPath('name', 'Nama Sudah Dikoreksi');
});

test('entity berstatus pending_review TIDAK BISA diedit langsung', function () {
    [$tenant, $entity] = siapkanEntityUntukUpdate('pending_review');

    $response = $this->patchJson("/api/entities/{$entity->id}", [
        'name' => 'Coba Diubah',
    ], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(422);
    expect($entity->refresh()->name)->toBe('Budi Santoso');
});

test('entity berstatus published TIDAK BISA diedit langsung', function () {
    [$tenant, $entity] = siapkanEntityUntukUpdate('published');

    $response = $this->patchJson("/api/entities/{$entity->id}", [
        'name' => 'Coba Diubah Diam-diam',
    ], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(422);
    expect($entity->refresh()->name)->toBe('Budi Santoso');
});

test('update parsial: kirim 1 field saja tidak mengosongkan field lain', function () {
    [$tenant, $entity] = siapkanEntityUntukUpdate('draft');

    $response = $this->patchJson("/api/entities/{$entity->id}", [
        'name' => 'Nama Baru Saja',
    ], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(200);
    $response->assertJsonPath('type', 'person'); // tetap sama, gak ikut kehapus
});

test('ganti region ke yang tenant tidak punya akses ditolak', function () {
    [$tenant, $entity] = siapkanEntityUntukUpdate('draft');
    $regionLain = Region::create(['name' => 'Jawa Barat', 'code' => 'ID-JB', 'level' => 'province']);

    $response = $this->patchJson("/api/entities/{$entity->id}", [
        'region_id' => $regionLain->id,
    ], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(422);
});