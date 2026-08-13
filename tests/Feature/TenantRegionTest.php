<?php

use App\Modules\TenantRegion\Models\Region;
use App\Modules\TenantRegion\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('region bisa dibuat dan punya UUID otomatis', function () {
    $region = Region::create([
        'name' => 'Indonesia',
        'code' => 'ID',
        'level' => 'country',
    ]);

    expect($region->id)->toBeString();
    expect(strlen($region->id))->toBe(36); // format UUID standar
});

test('region anak (child) bisa terhubung ke region induk (parent)', function () {
    $indonesia = Region::create([
        'name' => 'Indonesia',
        'code' => 'ID',
        'level' => 'country',
    ]);

    $banten = Region::create([
        'parent_id' => $indonesia->id,
        'name' => 'Banten',
        'code' => 'ID-BT',
        'level' => 'province',
    ]);

    expect($banten->parent->name)->toBe('Indonesia');
    expect($indonesia->children)->toHaveCount(1);
    expect($indonesia->children->first()->name)->toBe('Banten');
});

test('tenant bisa dibuat dan diberi akses ke region tertentu', function () {
    $tenant = Tenant::create([
        'name' => 'Research Tenant Banten',
        'slug' => 'tenant-banten',
    ]);
    $banten = Region::create([
        'name' => 'Banten',
        'code' => 'ID-BT',
        'level' => 'province',
    ]);
    aktifkanTenantContext($tenant->id);
    $tenant->regions()->attach($banten->id, ['id' => \Illuminate\Support\Str::uuid7(), 'access_level' => 'full']);

    expect($tenant->regions)->toHaveCount(1);
    expect($tenant->regions->first()->name)->toBe('Banten');
    expect($tenant->regions->first()->pivot->access_level)->toBe('full');
});

test('tenant TIDAK otomatis punya akses ke region yang belum di-assign (DENY by default)', function () {
    $tenant = Tenant::create([
        'name' => 'Research Tenant Banten',
        'slug' => 'tenant-banten',
    ]);

    Region::create([
        'name' => 'Jawa Barat',
        'code' => 'ID-JB',
        'level' => 'province',
    ]);

    expect($tenant->regions)->toHaveCount(0);
});