<?php

namespace App\Modules\Relationship\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Relationship\Models\Relationship;
use App\Modules\Relationship\Services\RelationshipReviewService;
use Illuminate\Http\Request;

class RelationshipReviewController extends Controller
{
    public function __construct(
        private readonly RelationshipReviewService $relationshipReviewService
    ) {}

    public function submitForReview(string $id)
    {
        $relationship = Relationship::findOrFail($id);
        $relationship = $this->relationshipReviewService->submitForReview($relationship);

        return response()->json($relationship);
    }

    public function publish(string $id, Request $request)
    {
        $relationship = Relationship::findOrFail($id);
        $relationship = $this->relationshipReviewService->publish($relationship, $request->user());

        return response()->json($relationship);
    }

    public function requestRevision(string $id, Request $request)
    {
        $relationship = Relationship::findOrFail($id);
        $relationship = $this->relationshipReviewService->requestRevision($relationship, $request->user());

        return response()->json($relationship);
    }
}