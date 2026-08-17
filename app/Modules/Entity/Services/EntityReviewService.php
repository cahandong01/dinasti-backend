<?php

namespace App\Modules\Entity\Services;

use App\Models\User;
use App\Modules\Entity\Models\Entity;
use Illuminate\Validation\ValidationException;

class EntityReviewService
{
    /**
     * State machine (D7): draft -> pending_review -> published
     *                                    -> needs_revision
     *                     published -> needs_revision (correction/dispute)
     * SEMUA transisi divalidasi di sini — 1 tempat, bukan tersebar di
     * beberapa controller, biar tidak ada jalur nyelonong lewat aturan.
     */
    private const TRANSISI_VALID = [
        'draft' => ['pending_review'],
        'pending_review' => ['published', 'needs_revision'],
        'published' => ['needs_revision'],
        'needs_revision' => ['pending_review'],
    ];

    public function submitForReview(Entity $entity): Entity
    {
        return $this->transisi($entity, 'pending_review');
    }

    public function publish(Entity $entity, User $reviewer): Entity
    {
        return $this->transisi($entity, 'published', $reviewer);
    }

    public function requestRevision(Entity $entity, User $reviewer): Entity
    {
        return $this->transisi($entity, 'needs_revision', $reviewer);
    }

    private function transisi(Entity $entity, string $statusTujuan, ?User $reviewer = null): Entity
    {
        $statusSekarang = $entity->status;
        $bolehPindahKe = self::TRANSISI_VALID[$statusSekarang] ?? [];

        if (! in_array($statusTujuan, $bolehPindahKe, true)) {
            throw ValidationException::withMessages([
                'status' => "Tidak bisa pindah dari status '{$statusSekarang}' ke '{$statusTujuan}'.",
            ]);
        }

        $entity->status = $statusTujuan;

        if ($reviewer) {
            $entity->reviewed_by = $reviewer->id;
            $entity->reviewed_at = now();
        }

        // first_published_at WAJIB cuma keisi SEKALI (publikasi PERTAMA),
        // beda dari reviewed_at yang ke-overwrite tiap transisi. Dipakai
        // buat validasi batas waktu 2 bulan pengajuan Hak Jawab.
        if ($statusTujuan === 'published' && $entity->first_published_at === null) {
            $entity->first_published_at = now();
        }

        $entity->save();

        return $entity->refresh();
    }
}