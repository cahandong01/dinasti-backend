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

function siapkanTenantUntukNetwork(): array
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

function buatRelationshipUntukNetwork(Tenant $tenant, Entity $source, Entity $target): Relationship
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

test('traversal rantai A-B-C, depth 2 dari A menjangkau B dan C', function () {
    [$tenant, $region] = siapkanTenantUntukNetwork();

    $a = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'A']);
    $b = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'B']);
    $c = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'C']);
    buatRelationshipUntukNetwork($tenant, $a, $b);
    buatRelationshipUntukNetwork($tenant, $b, $c);

    $response = $this->getJson("/api/entities/{$a->id}/network?depth=2", ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(200);
    $namaEntity = collect($response->json('entities'))->pluck('name');
    expect($namaEntity)->toContain('A', 'B', 'C');
});

test('traversal dengan depth 1 dari A TIDAK menjangkau C (2 hop)', function () {
    [$tenant, $region] = siapkanTenantUntukNetwork();

    $a = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'A']);
    $b = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'B']);
    $c = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'C']);
    buatRelationshipUntukNetwork($tenant, $a, $b);
    buatRelationshipUntukNetwork($tenant, $b, $c);

    $response = $this->getJson("/api/entities/{$a->id}/network?depth=1", ['X-Tenant-ID' => $tenant->id]);

    $namaEntity = collect($response->json('entities'))->pluck('name');
    expect($namaEntity)->toContain('A', 'B');
    expect($namaEntity)->not->toContain('C');
});

test('graph bersiklus (A-B-C-A) tidak infinite loop, tiap entity muncul cuma 1x', function () {
    [$tenant, $region] = siapkanTenantUntukNetwork();

    $a = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'A']);
    $b = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'B']);
    $c = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'C']);
    buatRelationshipUntukNetwork($tenant, $a, $b);
    buatRelationshipUntukNetwork($tenant, $b, $c);
    buatRelationshipUntukNetwork($tenant, $c, $a); // nutup siklus balik ke A

    $response = $this->getJson("/api/entities/{$a->id}/network?depth=4", ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(200);
    $entities = collect($response->json('entities'));
    expect($entities)->toHaveCount(3); // A, B, C — masing-masing cuma sekali walau siklus
});

test('traversal bekerja dua arah — B bisa nemu A walau A adalah source, bukan target', function () {
    [$tenant, $region] = siapkanTenantUntukNetwork();

    $a = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'A']);
    $b = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'B']);
    buatRelationshipUntukNetwork($tenant, $a, $b); // A -> B

    $response = $this->getJson("/api/entities/{$b->id}/network?depth=1", ['X-Tenant-ID' => $tenant->id]);

    $namaEntity = collect($response->json('entities'))->pluck('name');
    expect($namaEntity)->toContain('A');
});

test('depth lebih dari 4 ditolak validasi (hard cap D1)', function () {
    [$tenant, $region] = siapkanTenantUntukNetwork();
    $a = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'A']);

    $response = $this->getJson("/api/entities/{$a->id}/network?depth=5", ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(422);
});

test('entity yang tidak ada dibalas 404', function () {
    [$tenant] = siapkanTenantUntukNetwork();

    $response = $this->getJson('/api/entities/' . \Illuminate\Support\Str::uuid() . '/network', ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(404);
});