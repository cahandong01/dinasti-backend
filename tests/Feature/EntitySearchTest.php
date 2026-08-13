<?php

use App\Models\User;
use App\Modules\Entity\Models\Entity;
use App\Modules\TenantRegion\Models\Region;
use App\Modules\TenantRegion\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function siapkanUserPenelitiDiTenant(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'RESEARCHER', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    setPermissionsTeamId($tenant->id);
    $user->assignRole('RESEARCHER');
    aktifkanTenantContext($tenant->id);

    return $user;
}

test('pencarian nama entity yang cocok mengembalikan hasil', function () {
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    $region = Region::create(['name' => 'Banten', 'code' => 'ID-BT', 'level' => 'province']);
    siapkanUserPenelitiDiTenant($tenant);

    Entity::create([
        'tenant_id' => $tenant->id,
        'region_id' => $region->id,
        'type' => 'person',
        'name' => 'Ratu Atut Chosiyah',
    ]);

    $response = $this->getJson('/api/entities/search?q=Ratu Atut', [
        'X-Tenant-ID' => $tenant->id,
    ]);

    $response->assertStatus(200);
    $response->assertJsonFragment(['name' => 'Ratu Atut Chosiyah']);
});

test('pencarian dengan typo tetap ketemu (fuzzy match pg_trgm)', function () {
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    $region = Region::create(['name' => 'Banten', 'code' => 'ID-BT', 'level' => 'province']);
    siapkanUserPenelitiDiTenant($tenant);

    Entity::create([
        'tenant_id' => $tenant->id,
        'region_id' => $region->id,
        'type' => 'person',
        'name' => 'Ratu Atut Chosiyah',
    ]);

    // Sengaja typo: "Ratu Atud" (d, bukan t)
    $response = $this->getJson('/api/entities/search?q=Ratu Atud', [
        'X-Tenant-ID' => $tenant->id,
    ]);

    $response->assertStatus(200);
    $response->assertJsonFragment(['name' => 'Ratu Atut Chosiyah']);
});

test('entity dari tenant lain tidak ikut muncul (RLS bekerja)', function () {
    $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);
    $region = Region::create(['name' => 'Banten', 'code' => 'ID-BT', 'level' => 'province']);

    // Entity dibuat di tenant B lewat SUPER_ADMIN sementara, biar lolos RLS pas insert
    Role::firstOrCreate(['name' => 'SUPER_ADMIN', 'guard_name' => 'web', 'tenant_id' => null]);
    $adminB = User::factory()->create();
    setPermissionsTeamId($tenantB->id);
    $adminB->assignRole('SUPER_ADMIN');
    \DB::statement("SET app.current_tenant = '{$tenantB->id}'");
    Entity::create([
        'tenant_id' => $tenantB->id,
        'region_id' => $region->id,
        'type' => 'person',
        'name' => 'Orang Rahasia Tenant B',
    ]);

    // Sekarang login sebagai researcher tenant A, cari nama yang sama persis
    siapkanUserPenelitiDiTenant($tenantA);

    $response = $this->getJson('/api/entities/search?q=Orang Rahasia', [
        'X-Tenant-ID' => $tenantA->id,
    ]);

    $response->assertStatus(200);
    $response->assertJsonMissing(['name' => 'Orang Rahasia Tenant B']);
});

test('kata kunci pencarian kurang dari 2 karakter ditolak validasi', function () {
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    siapkanUserPenelitiDiTenant($tenant);

    $response = $this->getJson('/api/entities/search?q=a', [
        'X-Tenant-ID' => $tenant->id,
    ]);

    $response->assertStatus(422);
});