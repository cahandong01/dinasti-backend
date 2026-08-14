<?php

use App\Models\User;
use App\Modules\TenantRegion\Models\Region;
use App\Modules\TenantRegion\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function siapkanTenantUntukRateLimit(): array
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

test('endpoint graph network kena rate limit setelah lewat batas authenticated (30/menit)', function () {
    [$tenant] = siapkanTenantUntukRateLimit();

    // Batas authenticated limiter 'graph' = 30/menit (config/rate_limits.php).
    // Entity ID acak sengaja dipakai (bakal 404) — throttle menghitung SEMUA
    // request yang lewat middleware, terlepas apapun hasil akhirnya.
    for ($i = 1; $i <= 30; $i++) {
        $this->getJson('/api/entities/' . \Illuminate\Support\Str::uuid() . '/network', [
            'X-Tenant-ID' => $tenant->id,
        ]);
    }

    $response = $this->getJson('/api/entities/' . \Illuminate\Support\Str::uuid() . '/network', [
        'X-Tenant-ID' => $tenant->id,
    ]);

    $response->assertStatus(429);
});

test('request ke-30 (masih dalam batas) TIDAK kena rate limit', function () {
    [$tenant] = siapkanTenantUntukRateLimit();

    for ($i = 1; $i <= 29; $i++) {
        $this->getJson('/api/entities/' . \Illuminate\Support\Str::uuid() . '/network', [
            'X-Tenant-ID' => $tenant->id,
        ]);
    }

    $response = $this->getJson('/api/entities/' . \Illuminate\Support\Str::uuid() . '/network', [
        'X-Tenant-ID' => $tenant->id,
    ]);

    $response->assertStatus(404); // bukan 429 — masih dalam batas 30/menit
});