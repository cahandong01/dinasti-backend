<?php

use App\Modules\Entity\Models\Entity;
use App\Modules\Evidence\Models\Evidence;
use App\Modules\Evidence\Models\Source;
use App\Modules\Relationship\Models\Relationship;
use App\Modules\TenantRegion\Models\Region;
use App\Modules\TenantRegion\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('stats homepage cuma hitung data published, publik tanpa login', function () {
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    DB::statement("SET app.current_tenant = '{$tenant->id}'");
    $provinsi = Region::create(['name' => 'Banten', 'code' => 'ID-BT', 'level' => 'province']);
    Region::create(['name' => 'Kota Serang', 'code' => 'ID-BT-01', 'level' => 'city', 'parent_id' => $provinsi->id]);

    $a = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $provinsi->id, 'type' => 'person', 'name' => 'A', 'status' => 'published']);
    $b = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $provinsi->id, 'type' => 'person', 'name' => 'B', 'status' => 'published']);
    Entity::create(['tenant_id' => $tenant->id, 'region_id' => $provinsi->id, 'type' => 'person', 'name' => 'C Draft', 'status' => 'draft']);

    $source = Source::create(['tenant_id' => $tenant->id, 'name' => 'Sumber A', 'type' => 'court_ruling']);
    $evidence = Evidence::create(['source_id' => $source->id, 'tenant_id' => $tenant->id, 'excerpt' => 'Bukti']);

    Relationship::create([
        'source_entity_id' => $a->id,
        'target_entity_id' => $b->id,
        'evidence_id' => $evidence->id,
        'type' => 'directorship',
        'status' => 'published',
    ]);

    DB::statement("RESET app.current_tenant"); // simulasi request publik beneran

    $response = $this->getJson('/api/homepage/stats');

    $response->assertStatus(200);
    $response->assertJsonPath('entities', 2); // A, B — bukan C (draft)
    $response->assertJsonPath('relationships', 1);
    $response->assertJsonPath('sources', 1);
    $response->assertJsonPath('regions', 1); // cuma level provinsi, bukan Kota Serang
});

test('preview network homepage balikin entity published paling banyak koneksi', function () {
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    DB::statement("SET app.current_tenant = '{$tenant->id}'");
    $region = Region::create(['name' => 'Banten', 'code' => 'ID-BT', 'level' => 'province']);

    $hub = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'Hub Ramai', 'status' => 'published']);
    $b = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'B', 'status' => 'published']);
    $c = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'C', 'status' => 'published']);
    $sepi = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'Sepi', 'status' => 'published']);

    $source = Source::create(['tenant_id' => $tenant->id, 'name' => 'Sumber', 'type' => 'court_ruling']);
    $evidence = Evidence::create(['source_id' => $source->id, 'tenant_id' => $tenant->id, 'excerpt' => 'Bukti']);

    Relationship::create(['source_entity_id' => $hub->id, 'target_entity_id' => $b->id, 'evidence_id' => $evidence->id, 'type' => 'directorship', 'status' => 'published']);
    Relationship::create(['source_entity_id' => $hub->id, 'target_entity_id' => $c->id, 'evidence_id' => $evidence->id, 'type' => 'directorship', 'status' => 'published']);

    DB::statement("RESET app.current_tenant");

    $response = $this->getJson('/api/homepage/preview-network');

    $response->assertStatus(200);
    $nama = collect($response->json('entities'))->pluck('name');
    expect($nama)->toContain('Hub Ramai', 'B', 'C');
    expect($nama)->not->toContain('Sepi');
});