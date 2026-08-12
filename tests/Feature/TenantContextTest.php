<?php

use App\Models\User;
use App\Modules\TenantRegion\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('request tanpa header X-Tenant-ID ditolak 400', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    Route::middleware(['auth:sanctum', 'tenant.context'])->get('/test-tenant-context', fn () => response()->json(['ok' => true]));

    $response = $this->getJson('/test-tenant-context');

    $response->assertStatus(400);
});

test('tenant yang tidak ada di database ditolak 404', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    Route::middleware(['auth:sanctum', 'tenant.context'])->get('/test-tenant-context', fn () => response()->json(['ok' => true]));

    $response = $this->getJson('/test-tenant-context', [
        'X-Tenant-ID' => (string) \Illuminate\Support\Str::uuid(),
    ]);

    $response->assertStatus(404);
});

test('user yang tidak punya role di tenant itu ditolak 403', function () {
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    Route::middleware(['auth:sanctum', 'tenant.context'])->get('/test-tenant-context', fn () => response()->json(['ok' => true]));

    $response = $this->getJson('/test-tenant-context', [
        'X-Tenant-ID' => $tenant->id,
    ]);

    $response->assertStatus(403);
});

test('user yang punya role di tenant itu lolos dan bisa akses endpoint', function () {
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    Role::firstOrCreate(['name' => 'RESEARCHER', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

    $user = User::factory()->create();
    Sanctum::actingAs($user);
    setPermissionsTeamId($tenant->id);
    $user->assignRole('RESEARCHER');

    Route::middleware(['auth:sanctum', 'tenant.context'])->get('/test-tenant-context', fn () => response()->json(['ok' => true]));

    $response = $this->getJson('/test-tenant-context', [
        'X-Tenant-ID' => $tenant->id,
    ]);

    $response->assertStatus(200);
    $response->assertJson(['ok' => true]);
});