<?php

use App\Modules\Entity\Models\Entity;
use App\Modules\TenantRegion\Models\Region;
use App\Modules\TenantRegion\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('tamu TANPA login bisa search entity published', function () {
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    DB::statement("SET app.current_tenant = '{$tenant->id}'");
    $region = Region::create(['name' => 'Banten', 'code' => 'ID-BT', 'level' => 'province']);
    Entity::create([
        'tenant_id' => $tenant->id,
        'region_id' => $region->id,
        'type' => 'person',
        'name' => 'Budi Santoso Published',
        'status' => 'published',
    ]);
    Entity::create([
        'tenant_id' => $tenant->id,
        'region_id' => $region->id,
        'type' => 'person',
        'name' => 'Budi Santoso Draft',
        'status' => 'draft',
    ]);
    DB::statement("RESET app.current_tenant"); // simulasi request publik beneran

    $response = $this->getJson('/api/entities/search?q=Santoso Published');

    $response->assertStatus(200);
    $nama = collect($response->json('data'))->pluck('name');
    expect($nama)->toContain('Budi Santoso Published');
    expect($nama)->not->toContain('Budi Santoso Draft'); // draft TETAP tersembunyi
});

test('tamu TANPA login bisa lihat detail entity published lewat slug', function () {
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    DB::statement("SET app.current_tenant = '{$tenant->id}'");
    $region = Region::create(['name' => 'Banten', 'code' => 'ID-BT', 'level' => 'province']);
    $entity = Entity::create([
        'tenant_id' => $tenant->id,
        'region_id' => $region->id,
        'type' => 'person',
        'name' => 'Ratu Atut Chosiyah',
        'status' => 'published',
    ]);
    DB::statement("RESET app.current_tenant");

    $response = $this->getJson("/api/entities/{$entity->slug}");

    $response->assertStatus(200);
    $response->assertJsonPath('name', 'Ratu Atut Chosiyah');
});

test('evidence dari entity draft TETAP tersembunyi dari publik, walau ada entity published lain', function () {
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    DB::statement("SET app.current_tenant = '{$tenant->id}'");
    $region = Region::create(['name' => 'Banten', 'code' => 'ID-BT', 'level' => 'province']);

    $entityDraft = Entity::create([
        'tenant_id' => $tenant->id,
        'region_id' => $region->id,
        'type' => 'person',
        'name' => 'Entity Draft Rahasia',
        'status' => 'draft',
    ]);

    $source = \App\Modules\Evidence\Models\Source::create([
        'tenant_id' => $tenant->id,
        'name' => 'Sumber Rahasia',
        'type' => 'court_ruling',
    ]);
    $evidence = \App\Modules\Evidence\Models\Evidence::create([
        'source_id' => $source->id,
        'tenant_id' => $tenant->id,
        'excerpt' => 'Bukti rahasia yang belum boleh publik',
    ]);
    \App\Modules\Entity\Models\EntityAttribute::create([
        'entity_id' => $entityDraft->id,
        'evidence_id' => $evidence->id,
        'attribute_key' => 'position',
        'attribute_value' => 'Rahasia',
        'valid_from' => '2020-01-01',
    ]);

    DB::statement("RESET app.current_tenant");

    // Evidence-nya TIDAK BOLEH kebaca sama sekali lewat query publik langsung
    $evidenceTerlihat = \App\Modules\Evidence\Models\Evidence::find($evidence->id);
    expect($evidenceTerlihat)->toBeNull();

    $sourceTerlihat = \App\Modules\Evidence\Models\Source::find($source->id);
    expect($sourceTerlihat)->toBeNull();
});

test('tamu TANPA login TIDAK bisa lihat detail entity yang masih draft', function () {
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    DB::statement("SET app.current_tenant = '{$tenant->id}'");
    $region = Region::create(['name' => 'Banten', 'code' => 'ID-BT', 'level' => 'province']);
    $entity = Entity::create([
        'tenant_id' => $tenant->id,
        'region_id' => $region->id,
        'type' => 'person',
        'name' => 'Masih Draft',
        'status' => 'draft',
    ]);
    DB::statement("RESET app.current_tenant");

    $response = $this->getJson("/api/entities/{$entity->slug}");

    $response->assertStatus(404);
});