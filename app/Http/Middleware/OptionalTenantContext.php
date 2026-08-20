<?php

namespace App\Http\Middleware;

use App\Modules\TenantRegion\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class OptionalTenantContext
{
    /**
     * Versi "optional" dari TenantContext — dipakai buat route yang
     * HARUS bisa diakses TAMU (publik, cuma lihat data published)
     * MAUPUN user login (internal, lihat semua status di tenant-nya).
     *
     * $request->user('sanctum') dipanggil LANGSUNG tanpa middleware
     * auth:sanctum di depannya — ini pola resmi "optional auth" Sanctum,
     * guard tetap resolve token kalau ada, balikin null kalau tidak
     * (bukan lempar 401).
     *
     * Beda dari TenantContext biasa: TIDAK ADA token -> diteruskan
     * sebagai TAMU (bukan ditolak). ADA token tapi X-Tenant-ID salah
     * -> tetap ditolak (asumsinya memang berniat pakai mode login).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');

        if (! $user) {
            // TAMU — tidak ada tenant context/RLS session var. Service
            // di controller WAJIB fallback ke mode publik (status
            // published saja, manfaatin RLS carve-out).
            $request->attributes->set('is_guest', true);

            return $next($request);
        }

        $tenantId = $request->header('X-Tenant-ID');

        if (empty($tenantId)) {
            return response()->json(['message' => 'Header X-Tenant-ID wajib diisi.'], 400);
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 404);
        }

        setPermissionsTeamId($tenant->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        // Pola query pivot langsung (Insiden #11) — JANGAN $user->roles().
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
            return response()->json(['message' => 'Anda tidak punya akses ke tenant ini.'], 403);
        }

        DB::statement("SET app.current_tenant = '{$tenant->id}'");
        $request->attributes->set('current_tenant', $tenant);
        $request->attributes->set('is_guest', false);

        return $next($request);
    }
}