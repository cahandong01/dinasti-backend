<?php

use App\Models\User;
use App\Modules\Entity\Models\Entity;
use App\Modules\Entity\Models\EntityAttribute;
use App\Modules\Evidence\Models\Evidence;
use App\Modules\Evidence\Models\Source;
use App\Modules\Relationship\Models\Relationship;
use App\Modules\TenantRegion\Models\Region;
use App\Modules\TenantRegion\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function siapkanUserDanTenantUntukDetail(): array
{
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    aktifkanTenantContext($tenant->id);
    Role::firstOrCreate(['name' => 'RESEARCHER', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

    $user = User::factory()->create();
    Sanctum::actingAs($user);
    setPermissionsTeamId($tenant->id);
    $user->assignRole('RESEARCHER');

    return [$tenant, Region::create(['name' => 'Banten', 'code' => 'ID-BT', 'level' => 'province'])];
}

test('detail entity lengkap dengan atribut dan evidence-nya', function () {
    [$tenant, $region] = siapkanUserDanTenantUntukDetail();

    $entity = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'Ratu Atut Chosiyah']);
    $source = Source::create(['tenant_id' => $tenant->id, 'name' => 'Putusan Pengadilan No. 123', 'type' => 'court_ruling']);
    $evidence = Evidence::create(['source_id' => $source->id, 'tenant_id' => $tenant->id, 'excerpt' => 'Menjabat sejak 2020']);
    EntityAttribute::create([
        'entity_id' => $entity->id,
        'evidence_id' => $evidence->id,
        'attribute_key' => 'position',
        'attribute_value' => 'Gubernur',
        'valid_from' => '2020-01-01',
    ]);

    $response = $this->getJson("/api/entities/{$entity->id}", ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(200);
    $response->assertJsonPath('name', 'Ratu Atut Chosiyah');
    $response->assertJsonPath('attributes.0.attribute_value', 'Gubernur');
    $response->assertJsonPath('attributes.0.evidence.source.name', 'Putusan Pengadilan No. 123');
});

test('detail entity menampilkan relationship dua arah (sebagai source maupun target)', function () {
    [$tenant, $region] = siapkanUserDanTenantUntukDetail();

    $orang = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'Budi Santoso']);
    $perusahaan = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'company', 'name' => 'PT Sejahtera']);
    $source = Source::create(['tenant_id' => $tenant->id, 'name' => 'Akta Perusahaan', 'type' => 'company_record']);
    $evidence = Evidence::create(['source_id' => $source->id, 'tenant_id' => $tenant->id, 'excerpt' => 'Budi adalah direktur']);

    Relationship::create([
        'source_entity_id' => $orang->id,
        'target_entity_id' => $perusahaan->id,
        'evidence_id' => $evidence->id,
        'type' => 'directorship',
        'valid_from' => '2020-01-01',
    ]);

    // Dilihat dari sisi "orang" (source)
    $responseOrang = $this->getJson("/api/entities/{$orang->id}", ['X-Tenant-ID' => $tenant->id]);
    $responseOrang->assertJsonPath('relationships_as_source.0.target_entity.name', 'PT Sejahtera');

    // Dilihat dari sisi "perusahaan" (target) — relationship yang sama harus tetap kelihatan dari arah sebaliknya
    $responseUsaha = $this->getJson("/api/entities/{$perusahaan->id}", ['X-Tenant-ID' => $tenant->id]);
    $responseUsaha->assertJsonPath('relationships_as_target.0.source_entity.name', 'Budi Santoso');
});

test('entity yang tidak ada dibalas 404', function () {
    [$tenant] = siapkanUserDanTenantUntukDetail();

    $response = $this->getJson('/api/entities/' . \Illuminate\Support\Str::uuid(), ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(404);
});

test('entity milik tenant lain dibalas 404, bukan bocor lewat detail API (RLS)', function () {
    $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);
    $region = Region::create(['name' => 'Banten', 'code' => 'ID-BT', 'level' => 'province']);

    Role::firstOrCreate(['name' => 'SUPER_ADMIN', 'guard_name' => 'web', 'tenant_id' => null]);
    $adminB = User::factory()->create();
    setPermissionsTeamId($tenantB->id);
    $adminB->assignRole('SUPER_ADMIN');
    aktifkanTenantContext($tenantB->id);
    $entityRahasia = Entity::create(['tenant_id' => $tenantB->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'Rahasia Tenant B']);

    Role::firstOrCreate(['name' => 'RESEARCHER', 'guard_name' => 'web', 'tenant_id' => $tenantA->id]);
    $userA = User::factory()->create();
    Sanctum::actingAs($userA);
    setPermissionsTeamId($tenantA->id);
    $userA->assignRole('RESEARCHER');

    $response = $this->getJson("/api/entities/{$entityRahasia->id}", ['X-Tenant-ID' => $tenantA->id]);

    $response->assertStatus(404);
});