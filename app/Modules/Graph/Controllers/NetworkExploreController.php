<?php

namespace App\Modules\Graph\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Entity\Models\Entity;
use App\Modules\Graph\Requests\NetworkExploreRequest;
use App\Modules\Graph\Services\NetworkExploreService;

class NetworkExploreController extends Controller
{
    public function __construct(
        private readonly NetworkExploreService $networkExploreService
    ) {}

    public function explore(string $id, NetworkExploreRequest $request)
    {
        if (! Entity::find($id)) {
            return response()->json(['message' => 'Entity tidak ditemukan.'], 404);
        }

        $hasil = $this->networkExploreService->explore($id, $request->depthOrDefault());

        return response()->json($hasil);
    }
}