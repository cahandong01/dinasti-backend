<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class HasRole
{
    /**
     * Pengganti Spatie role:X|Y bawaan (\Spatie\Permission\Middleware\RoleMiddleware).
     *
     * BUG SPATIE v8 TEAMS MODE (dibuktikan lewat Tinker, sesi 3):
     * `$user->roles()` DIAM-DIAM nyuntik filter
     * `model_has_roles.tenant_id = current_team` di level relasi —
     * filter ini ke-AND SEBELUM kondisi whereNull/orWhere manapun
     * yang kita tempel di atasnya, jadi TIDAK BISA di-override lewat
     * query builder biasa. Role global (tenant_id NULL di pivot,
     * misal SUPER_ADMIN) otomatis ke-exclude begitu context pindah
     * ke tenant manapun.
     *
     * FIX: query LANGSUNG ke tabel model_has_roles pakai DB facade,
     * BYPASS TOTAL dari relasi Eloquent $user->roles() yang
     * ter-scope itu. Dibuktikan benar lewat Tinker sebelum ditulis
     * ke sini (bukan asumsi).
     */
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        $roleNames = explode('|', $roles);
        $user = $request->user();

        $hasRole = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', User::class)
            ->where(function ($query) {
                $query->whereNull('model_has_roles.tenant_id')
                    ->orWhere('model_has_roles.tenant_id', getPermissionsTeamId());
            })
            ->whereIn('roles.name', $roleNames)
            ->exists();

        if (! $hasRole) {
            return response()->json(['message' => 'Anda tidak punya izin untuk aksi ini.'], 403);
        }

        return $next($request);
    }
}