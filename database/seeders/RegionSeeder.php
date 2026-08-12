<?php

namespace Database\Seeders;

use App\Modules\TenantRegion\Models\Region;
use App\Modules\TenantRegion\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $indonesia = Region::create([
            'name' => 'Indonesia',
            'code' => 'ID',
            'level' => 'country',
        ]);

        $banten = Region::create([
            'parent_id' => $indonesia->id,
            'name' => 'Banten',
            'code' => '36',
            'level' => 'province',
        ]);

        $kotaSerang = Region::create([
            'parent_id' => $banten->id,
            'name' => 'Kota Serang',
            'code' => '36.73',
            'level' => 'city',
        ]);

        $tangsel = Region::create([
            'parent_id' => $banten->id,
            'name' => 'Kota Tangerang Selatan',
            'code' => '36.74',
            'level' => 'city',
        ]);

        $lebak = Region::create([
            'parent_id' => $banten->id,
            'name' => 'Kabupaten Lebak',
            'code' => '36.02',
            'level' => 'regency',
        ]);

        $tenant = Tenant::create([
            'name' => 'Research Tenant Banten',
            'slug' => 'tenant-banten',
            'status' => 'active',
        ]);

        // Beri tenant akses penuh ke semua region Banten yang baru di-seed
        foreach ([$banten, $kotaSerang, $tangsel, $lebak] as $region) {
            $tenant->regions()->attach($region->id, [
                'id' => Str::uuid7(),
                'access_level' => 'full',
            ]);
        }
    }
}