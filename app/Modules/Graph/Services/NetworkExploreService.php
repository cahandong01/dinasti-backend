<?php

namespace App\Modules\Graph\Services;

use App\Modules\Entity\Models\Entity;
use App\Modules\Relationship\Models\Relationship;
use Illuminate\Support\Facades\DB;

class NetworkExploreService
{
    /**
     * Recursive CTE traversal dua arah (D1). Array `path` mencegah
     * infinite loop di graph yang bersiklus (A->B->C->A). Depth
     * dibatasi WHERE n.depth < ? — hard cap di level query, bukan
     * cuma dipotong belakangan di PHP.
     */
    public function explore(string $startEntityId, int $maxDepth): array
    {
        $hasil = DB::select('
            WITH RECURSIVE network AS (
                SELECT id AS entity_id, ARRAY[id] AS path, 0 AS depth
                FROM entities
                WHERE id = ?

                UNION ALL

                SELECT
                    CASE WHEN r.source_entity_id = n.entity_id THEN r.target_entity_id ELSE r.source_entity_id END,
                    n.path || (CASE WHEN r.source_entity_id = n.entity_id THEN r.target_entity_id ELSE r.source_entity_id END),
                    n.depth + 1
                FROM network n
                JOIN relationships r
                    ON (r.source_entity_id = n.entity_id OR r.target_entity_id = n.entity_id)
                WHERE n.depth < ?
                  AND NOT (
                      (CASE WHEN r.source_entity_id = n.entity_id THEN r.target_entity_id ELSE r.source_entity_id END) = ANY(n.path)
                  )
            )
            SELECT entity_id, MIN(depth) AS depth
            FROM network
            GROUP BY entity_id
            ORDER BY depth
        ', [$startEntityId, $maxDepth]);

        $entityIds = array_map(fn ($baris) => $baris->entity_id, $hasil);
        $depthPerEntity = array_column($hasil, 'depth', 'entity_id');

        $entities = Entity::whereIn('id', $entityIds)->get()->map(function (Entity $entity) use ($depthPerEntity) {
            $entity->hop_distance = (int) $depthPerEntity[$entity->id];

            return $entity;
        });

        $relationships = Relationship::whereIn('source_entity_id', $entityIds)
            ->whereIn('target_entity_id', $entityIds)
            ->get();

        return [
            'entities' => $entities,
            'relationships' => $relationships,
        ];
    }
}