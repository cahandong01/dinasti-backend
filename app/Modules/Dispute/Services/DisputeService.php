<?php

namespace App\Modules\Dispute\Services;

use App\Models\User;
use App\Modules\Dispute\Models\Dispute;
use App\Modules\Entity\Models\Entity;
use App\Modules\Entity\Services\EntityReviewService;
use App\Modules\Relationship\Models\Relationship;
use App\Modules\Relationship\Services\RelationshipReviewService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DisputeService
{
    /**
     * Peraturan Dewan Pers No 9/2008: Hak Jawab tidak berlaku lagi
     * 2 bulan sejak publikasi, kecuali disepakati lain para pihak.
     * Dihitung dari first_published_at (publikasi PERTAMA), bukan
     * reviewed_at yang bisa ke-overwrite kalau direvisi berkali-kali.
     * HANYA berlaku untuk type hak_jawab (API_CONTRACT.md Keputusan #3)
     * -- koreksi tidak ada batas waktu.
     */
    private const BATAS_WAKTU_BULAN = 2;

    public function __construct(
        private readonly EntityReviewService $entityReviewService,
        private readonly RelationshipReviewService $relationshipReviewService,
    ) {}

    public function create(
        string $type,
        string $disputableType,
        string $disputableId,
        string $name,
        string $email,
        string $phone,
        ?string $disputedPart,
        string $supportingEvidence,
        string $responseContent,
        bool $isSelfReported,
    ): Dispute {
        $disputable = $this->resolveDisputable($disputableType, $disputableId);

        if ($disputable->status !== 'published') {
            throw ValidationException::withMessages([
                'disputable_id' => 'Hanya data yang sudah dipublikasikan yang bisa disengketakan.',
            ]);
        }

        if ($type === Dispute::TYPE_HAK_JAWAB) {
            if (
                $disputable->first_published_at === null
                || $disputable->first_published_at->lt(now()->subMonths(self::BATAS_WAKTU_BULAN))
            ) {
                throw ValidationException::withMessages([
                    'disputable_id' => 'Batas waktu pengajuan Hak Jawab (2 bulan sejak publikasi) sudah lewat.',
                ]);
            }
        }

        // TANPA ->refresh() di sini (beda dari pola create() Entity/
        // Relationship) — endpoint ini PUBLIK, app.current_tenant
        // tidak ter-set, jadi refresh() (query SELECT ulang) akan
        // ditolak RLS dan bikin ModelNotFoundException -> 404 palsu.
        // Semua nilai yang dibutuhkan sudah ada di memori dari array
        // di atas (status di-set eksplisit, bukan default DB).
        return Dispute::create([
            'tenant_id' => $disputable->tenant_id,
            'disputable_type' => $disputableType,
            'disputable_id' => $disputable->id,
            'type' => $type,
            'tracking_token' => Str::random(40),
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'disputed_part' => $disputedPart,
            'supporting_evidence' => $supportingEvidence,
            'response_content' => $responseContent,
            'is_self_reported' => $isSelfReported,
            'status' => Dispute::STATUS_PENDING,
        ]);
    }

    /**
     * Cek status pengajuan secara PUBLIK — cukup modal tracking_token
     * (API_CONTRACT.md Keputusan #2), bukan email (hindari PII di URL).
     */
    public function findByTrackingToken(string $token): ?Dispute
    {
        return Dispute::where('tracking_token', $token)->first();
    }

    /**
     * Riwayat dispute per-entity, PUBLIK (API_CONTRACT.md Keputusan #7).
     * WAJIB query VIEW entity_disputes_public (bukan model Dispute
     * langsung) — VIEW ini secara struktural TIDAK PUNYA kolom PII
     * (name/email/phone/tracking_token/resolved_by/is_self_reported),
     * jadi tidak mungkin bocor lewat method ini walau ada bug lain.
     */
    public function getPublicHistoryForDisputable(string $disputableType, string $disputableId): array
    {
        return DB::table('entity_disputes_public')
            ->where('disputable_type', $disputableType)
            ->where('disputable_id', $disputableId)
            ->orderByDesc('resolved_at')
            ->get()
            ->toArray();
    }

    public function approve(string $disputeId, User $resolver, ?string $note): Dispute
    {
        $dispute = $this->findPending($disputeId);
        $disputable = $this->resolveDisputable($dispute->disputable_type, $dispute->disputable_id);

        if ($disputable instanceof Entity) {
            $this->entityReviewService->requestRevision($disputable, $resolver);
        } else {
            $this->relationshipReviewService->requestRevision($disputable, $resolver);
        }

        $dispute->status = Dispute::STATUS_RESOLVED_ACCEPTED;
        $dispute->resolved_by = $resolver->id;
        $dispute->resolved_at = now();
        $dispute->resolution_note = $note;
        $dispute->save();

        return $dispute->refresh();
    }

    public function reject(string $disputeId, User $resolver, ?string $note): Dispute
    {
        $dispute = $this->findPending($disputeId);

        $dispute->status = Dispute::STATUS_RESOLVED_REJECTED;
        $dispute->resolved_by = $resolver->id;
        $dispute->resolved_at = now();
        $dispute->resolution_note = $note;
        $dispute->save();

        return $dispute->refresh();
    }

    private function findPending(string $disputeId): Dispute
    {
        $dispute = Dispute::find($disputeId);

        if (! $dispute || $dispute->status !== Dispute::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'dispute' => 'Dispute tidak ditemukan atau sudah diselesaikan sebelumnya.',
            ]);
        }

        return $dispute;
    }

    private function resolveDisputable(string $type, string $id): Entity|Relationship
    {
        $modelClass = Relation::getMorphedModel($type);

        if (! $modelClass) {
            throw ValidationException::withMessages([
                'disputable_type' => 'Tipe data tidak valid.',
            ]);
        }

        $disputable = $modelClass::find($id);

        if (! $disputable) {
            throw ValidationException::withMessages([
                'disputable_id' => 'Data yang mau disengketakan tidak ditemukan.',
            ]);
        }

        return $disputable;
    }
}