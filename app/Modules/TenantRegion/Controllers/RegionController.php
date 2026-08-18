<?php

namespace App\Modules\TenantRegion\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TenantRegion\Models\Region;
use Illuminate\Http\Request;

class RegionController extends Controller
{
    /**
     * Endpoint publik (API_CONTRACT.md #6) — dukung cascading dropdown
     * Provinsi -> Kab/Kota -> Kecamatan -> Desa. Tanpa parent_id,
     * balikin level teratas (provinsi, parent_id null).
     */
    public function index(Request $request)
    {
        $parentId = $request->query('parent_id');

        $regions = Region::where('parent_id', $parentId)
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'code', 'level']);

        return response()->json(['data' => $regions]);
    }
}