<?php

namespace App\Modules\Relationship\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Relationship\Models\Relationship;
use App\Modules\Relationship\Requests\RelationshipUpdateRequest;
use App\Modules\Relationship\Services\RelationshipUpdateService;

class RelationshipUpdateController extends Controller
{
    public function __construct(
        private readonly RelationshipUpdateService $relationshipUpdateService
    ) {}

    public function update(string $id, RelationshipUpdateRequest $request)
    {
        $relationship = Relationship::findOrFail($id);
        $relationship = $this->relationshipUpdateService->update($relationship, $request->validated());

        return response()->json($relationship);
    }
}