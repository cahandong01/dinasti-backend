<?php

namespace App\Modules\Entity\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Entity\Models\Entity;
use App\Modules\Entity\Services\EntityReviewService;
use Illuminate\Http\Request;

class EntityReviewController extends Controller
{
    public function __construct(
        private readonly EntityReviewService $entityReviewService
    ) {}

    public function submitForReview(string $id)
    {
        $entity = Entity::findOrFail($id);
        $entity = $this->entityReviewService->submitForReview($entity);

        return response()->json($entity);
    }

    public function publish(string $id, Request $request)
    {
        $entity = Entity::findOrFail($id);
        $entity = $this->entityReviewService->publish($entity, $request->user());

        return response()->json($entity);
    }

    public function requestRevision(string $id, Request $request)
    {
        $entity = Entity::findOrFail($id);
        $entity = $this->entityReviewService->requestRevision($entity, $request->user());

        return response()->json($entity);
    }
}