<?php

namespace App\Modules\Homepage\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Graph\Services\NetworkExploreService;
use App\Modules\Homepage\Services\HomepageStatsService;

class HomepageStatsController extends Controller
{
    public function __construct(
        private readonly HomepageStatsService $homepageStatsService,
        private readonly NetworkExploreService $networkExploreService,
    ) {}

    public function stats()
    {
        return response()->json($this->homepageStatsService->getStats());
    }

    /**
     * Cuplikan network buat hero homepage — entity published dengan
     * koneksi published TERBANYAK, depth 2 (konsisten default Explore
     * Network). Manfaatin NetworkExploreService yang SUDAH ADA, bukan
     * nulis ulang recursive CTE.
     */
    public function previewNetwork()
    {
        $topEntityId = $this->homepageStatsService->findMostConnectedPublishedEntityId();

        if (! $topEntityId) {
            return response()->json(['entities' => [], 'relationships' => []]);
        }

        return response()->json($this->networkExploreService->explore($topEntityId, 2));
    }
}