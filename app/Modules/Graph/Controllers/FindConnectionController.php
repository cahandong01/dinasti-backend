<?php

namespace App\Modules\Graph\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Entity\Models\Entity;
use App\Modules\Graph\Requests\FindConnectionRequest;
use App\Modules\Graph\Services\FindConnectionService;

class FindConnectionController extends Controller
{
    public function __construct(
        private readonly FindConnectionService $findConnectionService
    ) {}

    public function find(string $id, FindConnectionRequest $request)
    {
        if (! Entity::find($id)) {
            return response()->json(['message' => 'Entity sumber tidak ditemukan.'], 404);
        }

        $targetId = $request->validated('target_id');

        if (! Entity::find($targetId)) {
            return response()->json(['message' => 'Entity target tidak ditemukan.'], 404);
        }

        $hasil = $this->findConnectionService->find($id, $targetId);

        return response()->json($hasil);
    }
}