<?php

namespace App\Modules\Relationship\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Relationship\Requests\RelationshipCreateRequest;
use App\Modules\Relationship\Services\RelationshipCreateService;

class RelationshipCreateController extends Controller
{
    public function __construct(
        private readonly RelationshipCreateService $relationshipCreateService
    ) {}

    public function store(RelationshipCreateRequest $request)
    {
        $relationship = $this->relationshipCreateService->create(
            sourceEntityId: $request->validated('source_entity_id'),
            targetEntityId: $request->validated('target_entity_id'),
            evidenceId: $request->validated('evidence_id'),
            type: $request->validated('type'),
            validFrom: $request->validated('valid_from'),
            validUntil: $request->validated('valid_until'),
        );

        return response()->json($relationship, 201);
    }
}