<?php

namespace App\Modules\Relationship\Services;

use App\Modules\Relationship\Models\Relationship;

class RelationshipCreateService
{
    /**
     * Bikin relationship baru. Status SELALU 'draft' (default database,
     * D7) — sama seperti EntityCreateService, sengaja tidak menerima
     * status dari input. tenant_id otomatis keisi lewat static::creating()
     * hook di model Relationship (dari source_entity_id).
     *
     * Validasi "kedua entity milik tenant yang sama" TIDAK perlu ditulis
     * manual di sini — RLS sudah menjamin FormRequest hanya menerima ID
     * entity yang memang kelihatan di tenant aktif (lihat catatan di
     * RelationshipCreateRequest).
     */
    public function create(
        string $sourceEntityId,
        string $targetEntityId,
        string $evidenceId,
        string $type,
        ?string $validFrom,
        ?string $validUntil,
    ): Relationship {
        $relationship = Relationship::create([
            'source_entity_id' => $sourceEntityId,
            'target_entity_id' => $targetEntityId,
            'evidence_id' => $evidenceId,
            'type' => $type,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            // 'status' SENGAJA tidak diisi — biar database yang tentukan default 'draft'
        ]);

        return $relationship->refresh();
    }
}