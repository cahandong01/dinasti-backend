<?php

namespace App\Modules\Entity\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Entity\Requests\EntitySearchRequest;
use App\Modules\Entity\Services\EntitySearchService;

class EntitySearchController extends Controller
{
    public function __construct(
        private readonly EntitySearchService $entitySearchService
    ) {}

    public function search(EntitySearchRequest $request)
    {
        $results = $this->entitySearchService->search(
            query: $request->validated('q'),
            type: $request->validated('type'),
            perPage: $request->validated('per_page', 15),
        );

        return response()->json($results);
    }
}