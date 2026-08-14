<?php

namespace App\Modules\Entity\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Entity\Models\Entity;
use App\Modules\Entity\Requests\EntityUpdateRequest;
use App\Modules\Entity\Services\EntityUpdateService;

class EntityUpdateController extends Controller
{
    public function __construct(
        private readonly EntityUpdateService $entityUpdateService
    ) {}

    public function update(string $id, EntityUpdateRequest $request)
    {
        $entity = Entity::findOrFail($id);
        $entity = $this->entityUpdateService->update($entity, $request->validated());

        return response()->json($entity);
    }
}