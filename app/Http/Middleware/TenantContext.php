<?php

namespace App\Http\Middleware;

use App\Modules\TenantRegion\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TenantContext
{
    /**
     * E08 — Tenant Context Middleware.
     * Baca header X-Tenant-ID, validasi user login memang punya role di
     * tenant itu (bukan asal percaya header), lalu aktifkan context RBAC
     * (setPermissionsTeamId) dan context PostgreSQL RLS (app.current_tenant).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->header('X-Tenant-ID');

        if (empty($tenantId)) {
            return response()->json([
                'message' => 'Header X-Tenant-ID wajib diisi.',
            ], 400);
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 404);
        }

        $user = $request->user();

        // Validasi: user beneran punya role di tenant ini, bukan cuma
        // percaya klaim header mentah-mentah.
        setPermissionsTeamId($tenant->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        $isSuperAdmin = $user->roles()->whereNull('roles.tenant_id')->where('roles.name', 'SUPER_ADMIN')->exists();

        if (! $isSuperAdmin && $user->roles()->count() === 0) {
            return response()->json([
                'message' => 'Anda tidak punya akses ke tenant ini.',
            ], 403);
        }

        // Lapis tambahan buat RLS PostgreSQL (D10) — bahkan kalau ada bug
        // query yang lupa filter tenant_id, database tetap menolak.
        DB::statement("SET app.current_tenant = '{$tenant->id}'");

        $request->attributes->set('current_tenant', $tenant);

        return $next($request);
    }
}