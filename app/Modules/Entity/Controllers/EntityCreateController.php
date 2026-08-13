<?php

namespace App\Modules\Entity\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Entity\Requests\EntityCreateRequest;
use App\Modules\Entity\Services\EntityCreateService;

class EntityCreateController extends Controller
{
    public function __construct(
        private readonly EntityCreateService $entityCreateService
    ) {}

    public function store(EntityCreateRequest $request)
    {
        $entity = $this->entityCreateService->create(
            tenant: $request->attributes->get('current_tenant'),
            name: $request->validated('name'),
            type: $request->validated('type'),
            regionId: $request->validated('region_id'),
        );

        return response()->json($entity, 201);
    }
}