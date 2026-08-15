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

function siapkanTenantUntukFindConnection(): array
{
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    aktifkanTenantContext($tenant->id);
    $region = Region::create(['name' => 'Banten', 'code' => 'ID-BT', 'level' => 'province']);

    Role::firstOrCreate(['name' => 'RESEARCHER', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    setPermissionsTeamId($tenant->id);
    $user->assignRole('RESEARCHER');

    return [$tenant, $region];
}

function buatRelationshipUntukFindConnection(Tenant $tenant, Entity $source, Entity $target): Relationship
{
    $srcRecord = Source::create(['tenant_id' => $tenant->id, 'name' => 'Sumber', 'type' => 'court_ruling']);
    $evidence = Evidence::create(['source_id' => $srcRecord->id, 'tenant_id' => $tenant->id, 'excerpt' => 'Bukti']);

    return Relationship::create([
        'source_entity_id' => $source->id,
        'target_entity_id' => $target->id,
        'evidence_id' => $evidence->id,
        'type' => 'directorship',
    ]);
}

test('koneksi langsung A-B ketemu, depth 1', function () {
    [$tenant, $region] = siapkanTenantUntukFindConnection();
    $a = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'A']);
    $b = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'B']);
    buatRelationshipUntukFindConnection($tenant, $a, $b);

    $response = $this->getJson("/api/entities/{$a->id}/find-connection?target_id={$b->id}", ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(200);
    $response->assertJsonPath('connected', true);
    $response->assertJsonPath('depth', 1);
    expect(collect($response->json('entities'))->pluck('name'))->toEqual(collect(['A', 'B']));
});

test('koneksi 3-hop A-B-C-D ketemu, jalur lengkap sesuai urutan', function () {
    [$tenant, $region] = siapkanTenantUntukFindConnection();
    $a = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'A']);
    $b = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'B']);
    $c = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'C']);
    $d = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'D']);
    buatRelationshipUntukFindConnection($tenant, $a, $b);
    buatRelationshipUntukFindConnection($tenant, $b, $c);
    buatRelationshipUntukFindConnection($tenant, $c, $d);

    $response = $this->getJson("/api/entities/{$a->id}/find-connection?target_id={$d->id}", ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(200);
    $response->assertJsonPath('connected', true);
    $response->assertJsonPath('depth', 3);
    expect(collect($response->json('entities'))->pluck('name'))->toEqual(collect(['A', 'B', 'C', 'D']));
    expect($response->json('relationships'))->toHaveCount(3);
});

test('tidak ada jalur dalam 4 hop, tetap 200 dengan connected false', function () {
    [$tenant, $region] = siapkanTenantUntukFindConnection();
    $a = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'A']);
    $terpisah = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'Terpisah']);
    // Tidak ada relationship sama sekali antara A dan Terpisah

    $response = $this->getJson("/api/entities/{$a->id}/find-connection?target_id={$terpisah->id}", ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(200);
    $response->assertJsonPath('connected', false);
    $response->assertJsonPath('entities', []);
});

test('entity sumber tidak ada dibalas 404', function () {
    [$tenant, $region] = siapkanTenantUntukFindConnection();
    $b = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'B']);

    $response = $this->getJson('/api/entities/' . \Illuminate\Support\Str::uuid() . "/find-connection?target_id={$b->id}", ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(404);
});

test('entity target tidak ada dibalas 404', function () {
    [$tenant, $region] = siapkanTenantUntukFindConnection();
    $a = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'A']);

    $response = $this->getJson("/api/entities/{$a->id}/find-connection?target_id=" . \Illuminate\Support\Str::uuid(), ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(404);
});

test('target_id sama dengan source ditolak validasi', function () {
    [$tenant, $region] = siapkanTenantUntukFindConnection();
    $a = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'A']);

    $response = $this->getJson("/api/entities/{$a->id}/find-connection?target_id={$a->id}", ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(422);
});

test('entity target milik tenant lain dibalas 404, bukan bocor lewat find-connection (RLS)', function () {
    [$tenantA, $regionA] = siapkanTenantUntukFindConnection();
    $a = Entity::create(['tenant_id' => $tenantA->id, 'region_id' => $regionA->id, 'type' => 'person', 'name' => 'A']);

    // Tenant lain, entity lain, di luar konteks RLS tenant A
    $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);
    aktifkanTenantContext($tenantB->id);
    $regionB = Region::create(['name' => 'Jawa Barat', 'code' => 'ID-JB', 'level' => 'province']);
    $entityTenantLain = Entity::create(['tenant_id' => $tenantB->id, 'region_id' => $regionB->id, 'type' => 'person', 'name' => 'Rahasia']);

    // Balikin konteks ke tenant A buat request sebenarnya
    aktifkanTenantContext($tenantA->id);

    $response = $this->getJson("/api/entities/{$a->id}/find-connection?target_id={$entityTenantLain->id}", ['X-Tenant-ID' => $tenantA->id]);

    $response->assertStatus(404);
});