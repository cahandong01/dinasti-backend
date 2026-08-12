<?php

namespace Database\Seeders;

use App\Modules\TenantRegion\Models\Tenant;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Isi role sesuai D12 (DECISIONS.md).
     * SUPER_ADMIN = global (tenant_id null).
     * TENANT_ADMIN, RESEARCHER, LEGAL_REVIEWER = dibuat per tenant yang sudah ada.
     * PUBLIC_USER sengaja TIDAK dibuat sebagai role row — itu default kalau user
     * tidak login / tidak punya role apapun, bukan role yang di-assign.
     */
    public function run(): void
    {
        Role::firstOrCreate([
            'name' => 'SUPER_ADMIN',
            'guard_name' => 'web',
            'tenant_id' => null,
        ]);

        $tenantScopedRoles = ['TENANT_ADMIN', 'RESEARCHER', 'LEGAL_REVIEWER'];

        Tenant::all()->each(function (Tenant $tenant) use ($tenantScopedRoles) {
            foreach ($tenantScopedRoles as $roleName) {
                Role::firstOrCreate([
                    'name' => $roleName,
                    'guard_name' => 'web',
                    'tenant_id' => $tenant->id,
                ]);
            }
        });
    }
}