<?php

namespace App\Modules\Entity\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Entity\Services\EntityDetailService;

class EntityDetailController extends Controller
{
    public function __construct(
        private readonly EntityDetailService $entityDetailService
    ) {}

    public function show(string $id)
    {
        $entity = $this->entityDetailService->getDetail($id);

        if (! $entity) {
            return response()->json([
                'message' => 'Entity tidak ditemukan.',
            ], 404);
        }

        return response()->json($entity);
    }
}