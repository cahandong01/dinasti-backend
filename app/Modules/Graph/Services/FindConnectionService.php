<?php

namespace App\Modules\Graph\Services;

use App\Modules\Entity\Models\Entity;
use App\Modules\Relationship\Models\Relationship;
use Illuminate\Support\Facades\DB;

class FindConnectionService
{
    private const MAX_HOP = 4; // Hard cap D1 — TIDAK bisa diatur user (lihat DECISIONS.md D1)

    /**
     * Cari jalur TERPENDEK antara 2 entity spesifik. Pola CTE identik
     * NetworkExploreService (array `path` anti-cycle, depth hard-cap
     * di level query) — bedanya di sini kita JUGA lacak `rel_path`
     * (urutan relationship yang membentuk jalur itu), karena jawaban
     * yang dibutuhkan adalah JALUR SPESIFIK, bukan "siapa aja yang
     * reachable" seperti Explore Network.
     */
    public function find(string $sourceEntityId, string $targetEntityId): array
    {
        $baris = DB::selectOne('
            WITH RECURSIVE path_search AS (
                SELECT
                    id AS entity_id,
                    ARRAY[id] AS path,
                    ARRAY[]::uuid[] AS rel_path,
                    0 AS depth
                FROM entities
                WHERE id = ?
                UNION ALL
                SELECT
                    CASE WHEN r.source_entity_id = p.entity_id THEN r.target_entity_id ELSE r.source_entity_id END,
                    p.path || (CASE WHEN r.source_entity_id = p.entity_id THEN r.target_entity_id ELSE r.source_entity_id END),
                    p.rel_path || r.id,
                    p.depth + 1
                FROM path_search p
                JOIN relationships r
                    ON (r.source_entity_id = p.entity_id OR r.target_entity_id = p.entity_id)
                WHERE p.depth < ?
                  AND NOT (
                      (CASE WHEN r.source_entity_id = p.entity_id THEN r.target_entity_id ELSE r.source_entity_id END) = ANY(p.path)
                  )
            )
            SELECT path, rel_path, depth
            FROM path_search
            WHERE entity_id = ?
            ORDER BY depth ASC
            LIMIT 1
        ', [$sourceEntityId, self::MAX_HOP, $targetEntityId]);

        if (! $baris) {
            return [
                'connected' => false,
                'depth' => null,
                'entities' => [],
                'relationships' => [],
            ];
        }

        $entityIds = $this->parsePgArray($baris->path);
        $relationshipIds = $this->parsePgArray($baris->rel_path);

        // Urutan WAJIB dipertahankan sesuai jalur asli, bukan urutan
        // default hasil query (whereIn TIDAK menjamin urutan).
        $entities = Entity::whereIn('id', $entityIds)->get()
            ->sortBy(fn (Entity $entity) => array_search($entity->id, $entityIds))
            ->values();

        $relationships = Relationship::whereIn('id', $relationshipIds)->get()
            ->sortBy(fn (Relationship $relationship) => array_search($relationship->id, $relationshipIds))
            ->values();

        return [
            'connected' => true,
            'depth' => (int) $baris->depth,
            'entities' => $entities,
            'relationships' => $relationships,
        ];
    }

    /**
     * Parse kolom array PostgreSQL ("{uuid1,uuid2}") jadi array PHP
     * native. DB::selectOne() TIDAK auto-cast ini seperti Eloquent.
     */
    private function parsePgArray(string $pgArray): array
    {
        $trimmed = trim($pgArray, '{}');

        return $trimmed === '' ? [] : explode(',', $trimmed);
    }
}