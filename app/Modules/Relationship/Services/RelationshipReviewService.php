<?php

namespace App\Modules\Relationship\Services;

use App\Models\User;
use App\Modules\Relationship\Models\Relationship;
use Illuminate\Validation\ValidationException;

class RelationshipReviewService
{
    /**
     * State machine (D7) — sama persis pola EntityReviewService,
     * cuma bedanya buat model Relationship.
     */
    private const TRANSISI_VALID = [
        'draft' => ['pending_review'],
        'pending_review' => ['published', 'needs_revision'],
        'published' => ['needs_revision'],
        'needs_revision' => ['pending_review'],
    ];

    public function submitForReview(Relationship $relationship): Relationship
    {
        return $this->transisi($relationship, 'pending_review');
    }

    public function publish(Relationship $relationship, User $reviewer): Relationship
    {
        return $this->transisi($relationship, 'published', $reviewer);
    }

    public function requestRevision(Relationship $relationship, User $reviewer): Relationship
    {
        return $this->transisi($relationship, 'needs_revision', $reviewer);
    }

    private function transisi(Relationship $relationship, string $statusTujuan, ?User $reviewer = null): Relationship
    {
        $statusSekarang = $relationship->status;
        $bolehPindahKe = self::TRANSISI_VALID[$statusSekarang] ?? [];

        if (! in_array($statusTujuan, $bolehPindahKe, true)) {
            throw ValidationException::withMessages([
                'status' => "Tidak bisa pindah dari status '{$statusSekarang}' ke '{$statusTujuan}'.",
            ]);
        }

        $relationship->status = $statusTujuan;

        if ($reviewer) {
            $relationship->reviewed_by = $reviewer->id;
            $relationship->reviewed_at = now();
        }

        $relationship->save();

        return $relationship->refresh();
    }
}