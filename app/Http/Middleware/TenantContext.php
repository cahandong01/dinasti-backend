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

        setPermissionsTeamId($tenant->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        // FIX (sesi 3, dibuktikan lewat Tinker): $user->roles() TIDAK
        // BISA dipakai buat cek role global (tenant_id NULL) — Spatie
        // diam-diam nyuntik filter model_has_roles.tenant_id = current
        // team di level relasi, ke-AND SEBELUM kondisi whereNull kita.
        // Query LANGSUNG ke pivot table, bypass total dari relasi itu.
        $isSuperAdmin = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->whereNull('model_has_roles.tenant_id')
            ->where('roles.name', 'SUPER_ADMIN')
            ->exists();

        $punyaRoleDiTenantIni = DB::table('model_has_roles')
            ->where('model_id', $user->id)
            ->where(function ($query) use ($tenant) {
                $query->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id);
            })
            ->exists();

        if (! $isSuperAdmin && ! $punyaRoleDiTenantIni) {
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