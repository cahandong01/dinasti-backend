<?php

use App\Models\User;
use App\Modules\TenantRegion\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function buatRoleDasar(Tenant $tenantA, Tenant $tenantB): void
{
    Role::firstOrCreate(['name' => 'SUPER_ADMIN', 'guard_name' => 'web', 'tenant_id' => null]);
    foreach ([$tenantA, $tenantB] as $tenant) {
        foreach (['TENANT_ADMIN', 'RESEARCHER', 'LEGAL_REVIEWER'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        }
    }
}

test('user bisa punya role berbeda di tenant yang berbeda', function () {
    $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);
    buatRoleDasar($tenantA, $tenantB);

    $user = User::factory()->create();

    setPermissionsTeamId($tenantA->id);
    $user->assignRole('RESEARCHER');

    setPermissionsTeamId($tenantB->id);
    $user->unsetRelation('roles')->unsetRelation('permissions');
    $user->assignRole('TENANT_ADMIN');

    // Cek balik ke tenant A: harusnya cuma RESEARCHER, BUKAN TENANT_ADMIN
    setPermissionsTeamId($tenantA->id);
    $user->unsetRelation('roles')->unsetRelation('permissions');
    expect($user->hasRole('RESEARCHER'))->toBeTrue();
    expect($user->hasRole('TENANT_ADMIN'))->toBeFalse();

    // Cek di tenant B: harusnya cuma TENANT_ADMIN, BUKAN RESEARCHER
    setPermissionsTeamId($tenantB->id);
    $user->unsetRelation('roles')->unsetRelation('permissions');
    expect($user->hasRole('TENANT_ADMIN'))->toBeTrue();
    expect($user->hasRole('RESEARCHER'))->toBeFalse();
});

test('SUPER_ADMIN adalah role global, tidak nempel ke tenant manapun', function () {
    $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);
    buatRoleDasar($tenantA, $tenantB);

    $superAdminRole = Role::where('name', 'SUPER_ADMIN')->first();
    expect($superAdminRole->tenant_id)->toBeNull();
});

test('separation of duties: RESEARCHER tidak otomatis jadi LEGAL_REVIEWER', function () {
    $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);
    buatRoleDasar($tenantA, $tenantB);

    $user = User::factory()->create();

    setPermissionsTeamId($tenantA->id);
    $user->assignRole('RESEARCHER');

    expect($user->hasRole('RESEARCHER'))->toBeTrue();
    expect($user->hasRole('LEGAL_REVIEWER'))->toBeFalse();
});