<?php

use App\Modules\Entity\Models\Entity;
use App\Modules\TenantRegion\Models\Region;
use App\Modules\TenantRegion\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('entity published bisa dibaca TANPA app.current_tenant ter-set (akses publik)', function () {
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    DB::statement("SET app.current_tenant = '{$tenant->id}'");
    $region = Region::create(['name' => 'Banten', 'code' => 'ID-BT', 'level' => 'province']);
    $entity = Entity::create([
        'tenant_id' => $tenant->id,
        'region_id' => $region->id,
        'type' => 'person',
        'name' => 'Budi Published',
        'status' => 'published',
    ]);

    // SENGAJA reset context — simulasi request publik tanpa login/tenant
    DB::statement("RESET app.current_tenant");

    $hasil = Entity::find($entity->id);

    expect($hasil)->not->toBeNull();
    expect($hasil->name)->toBe('Budi Published');
});

test('entity draft TIDAK BISA dibaca tanpa app.current_tenant ter-set (tetap terkunci)', function () {
    $tenant = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);
    DB::statement("SET app.current_tenant = '{$tenant->id}'");
    $region = Region::create(['name' => 'Jawa Barat', 'code' => 'ID-JB', 'level' => 'province']);
    $entity = Entity::create([
        'tenant_id' => $tenant->id,
        'region_id' => $region->id,
        'type' => 'person',
        'name' => 'Budi Draft',
        'status' => 'draft',
    ]);

    DB::statement("RESET app.current_tenant");

    $hasil = Entity::find($entity->id);

    expect($hasil)->toBeNull();
});

test('entity draft milik tenant lain TETAP TIDAK BISA dibaca walau app.current_tenant ter-set ke tenant berbeda', function () {
    $tenantPemilik = Tenant::create(['name' => 'Tenant C', 'slug' => 'tenant-c']);
    DB::statement("SET app.current_tenant = '{$tenantPemilik->id}'");
    $region = Region::create(['name' => 'Jawa Tengah', 'code' => 'ID-JT', 'level' => 'province']);
    $entity = Entity::create([
        'tenant_id' => $tenantPemilik->id,
        'region_id' => $region->id,
        'type' => 'person',
        'name' => 'Rahasia Tenant C',
        'status' => 'draft',
    ]);

    $tenantLain = Tenant::create(['name' => 'Tenant D', 'slug' => 'tenant-d']);
    DB::statement("SET app.current_tenant = '{$tenantLain->id}'");

    $hasil = Entity::find($entity->id);

    expect($hasil)->toBeNull();
});