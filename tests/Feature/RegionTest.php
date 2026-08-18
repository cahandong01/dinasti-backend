<?php

use App\Modules\TenantRegion\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('list wilayah level teratas (provinsi) TANPA parent_id, publik tanpa login', function () {
    $provinsi = Region::create(['name' => 'Banten', 'code' => 'ID-BT', 'level' => 'province']);
    Region::create(['name' => 'Kota Serang', 'code' => 'ID-BT-01', 'level' => 'city', 'parent_id' => $provinsi->id]);

    $response = $this->getJson('/api/regions');

    $response->assertStatus(200);
    $namaWilayah = collect($response->json('data'))->pluck('name');
    expect($namaWilayah)->toContain('Banten');
    expect($namaWilayah)->not->toContain('Kota Serang'); // anak TIDAK ikut kalau tanpa parent_id
});

test('list wilayah anak dengan parent_id', function () {
    $provinsi = Region::create(['name' => 'Banten', 'code' => 'ID-BT', 'level' => 'province']);
    Region::create(['name' => 'Kota Serang', 'code' => 'ID-BT-01', 'level' => 'city', 'parent_id' => $provinsi->id]);
    Region::create(['name' => 'Jawa Barat', 'code' => 'ID-JB', 'level' => 'province']);

    $response = $this->getJson("/api/regions?parent_id={$provinsi->id}");

    $response->assertStatus(200);
    $namaWilayah = collect($response->json('data'))->pluck('name');
    expect($namaWilayah)->toContain('Kota Serang');
    expect($namaWilayah)->not->toContain('Jawa Barat'); // provinsi lain TIDAK ikut
});