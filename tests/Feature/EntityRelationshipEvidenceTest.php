<?php

use App\Modules\Entity\Models\Entity;
use App\Modules\Entity\Models\EntityAttribute;
use App\Modules\Evidence\Models\Evidence;
use App\Modules\Evidence\Models\Source;
use App\Modules\Relationship\Models\Relationship;
use App\Modules\TenantRegion\Models\Region;
use App\Modules\TenantRegion\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function buatTenantDanRegion(): array
{
    $tenant = Tenant::create(['name' => 'Research Tenant Banten', 'slug' => 'tenant-banten']);
    $region = Region::create(['name' => 'Banten', 'code' => 'ID-BT', 'level' => 'province']);

    return [$tenant, $region];
}

test('entity baru selalu berstatus draft secara default', function () {
    [$tenant, $region] = buatTenantDanRegion();

    $entity = Entity::create([
        'tenant_id' => $tenant->id,
        'region_id' => $region->id,
        'type' => 'person',
        'name' => 'Budi Santoso',
    ]);

    expect($entity->refresh()->status)->toBe('draft');
});

test('entity attribute wajib punya evidence, tidak bisa dibuat tanpa itu', function () {
    [$tenant, $region] = buatTenantDanRegion();

    $entity = Entity::create([
        'tenant_id' => $tenant->id,
        'region_id' => $region->id,
        'type' => 'person',
        'name' => 'Budi Santoso',
    ]);

    // Tanpa evidence_id, insert harus GAGAL karena foreign key constraint
    expect(fn () => EntityAttribute::create([
        'entity_id' => $entity->id,
        'attribute_key' => 'position',
        'attribute_value' => 'Direktur',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('entity attribute lengkap dengan evidence bisa dibuat dan ditelusuri sampai ke source', function () {
    [$tenant, $region] = buatTenantDanRegion();

    $entity = Entity::create([
        'tenant_id' => $tenant->id,
        'region_id' => $region->id,
        'type' => 'person',
        'name' => 'Budi Santoso',
    ]);

    $source = Source::create([
        'tenant_id' => $tenant->id,
        'name' => 'Putusan Pengadilan No. 123',
        'type' => 'court_ruling',
    ]);

    $evidence = Evidence::create([
        'source_id' => $source->id,
        'tenant_id' => $tenant->id,
        'excerpt' => 'Budi Santoso menjabat sebagai Direktur sejak 2020',
        'locator' => 'halaman 4',
    ]);

    $attribute = EntityAttribute::create([
        'entity_id' => $entity->id,
        'evidence_id' => $evidence->id,
        'attribute_key' => 'position',
        'attribute_value' => 'Direktur',
        'valid_from' => '2020-01-01',
    ]);

    expect($attribute->evidence->source->name)->toBe('Putusan Pengadilan No. 123');
    expect($entity->attributes)->toHaveCount(1);
});

test('relationship wajib punya evidence, tidak bisa dibuat tanpa itu', function () {
    [$tenant, $region] = buatTenantDanRegion();

    $orang = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'Budi Santoso']);
    $perusahaan = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'company', 'name' => 'PT Sejahtera']);

    expect(fn () => Relationship::create([
        'source_entity_id' => $orang->id,
        'target_entity_id' => $perusahaan->id,
        'type' => 'directorship',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('relationship baru selalu berstatus draft secara default', function () {
    [$tenant, $region] = buatTenantDanRegion();

    $orang = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'Budi Santoso']);
    $perusahaan = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'company', 'name' => 'PT Sejahtera']);

    $source = Source::create(['tenant_id' => $tenant->id, 'name' => 'Putusan Pengadilan No. 123', 'type' => 'court_ruling']);
    $evidence = Evidence::create(['source_id' => $source->id, 'tenant_id' => $tenant->id, 'excerpt' => 'Budi adalah direktur PT Sejahtera']);

    $relationship = Relationship::create([
        'source_entity_id' => $orang->id,
        'target_entity_id' => $perusahaan->id,
        'evidence_id' => $evidence->id,
        'type' => 'directorship',
        'valid_from' => '2020-01-01',
    ]);

    expect($relationship->refresh()->status)->toBe('draft');
    expect($relationship->sourceEntity->name)->toBe('Budi Santoso');
    expect($relationship->targetEntity->name)->toBe('PT Sejahtera');
});