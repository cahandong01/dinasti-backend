<?php

namespace App\Modules\Dispute\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dispute\Requests\CreateDisputeRequest;
use App\Modules\Dispute\Requests\ResolveDisputeRequest;
use App\Modules\Dispute\Services\DisputeService;
use Illuminate\Validation\ValidationException;

class DisputeController extends Controller
{
    public function __construct(
        private readonly DisputeService $disputeService
    ) {}

    public function store(CreateDisputeRequest $request)
    {
        try {
            $dispute = $this->disputeService->create(
                $request->validated('type'),
                $request->validated('disputable_type'),
                $request->validated('disputable_id'),
                $request->validated('name'),
                $request->validated('email'),
                $request->validated('phone'),
                $request->validated('disputed_part'),
                $request->validated('supporting_evidence'),
                $request->validated('response_content'),
                $request->validated('is_self_reported'),
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Pengajuan diterima, akan ditinjau LEGAL_REVIEWER.',
            'dispute_id' => $dispute->id,
            'type' => $dispute->type,
            'status' => $dispute->status,
            'tracking_url' => url("/api/disputes/status/{$dispute->tracking_token}"),
        ], 201);
    }

    /**
     * Cek status pengajuan PUBLIK (API_CONTRACT.md Keputusan #2) —
     * cukup modal tracking_token, TIDAK ADA data pribadi reviewer
     * yang di-expose.
     */
    public function status(string $token)
    {
        $dispute = $this->disputeService->findByTrackingToken($token);

        if (! $dispute) {
            return response()->json(['message' => 'Pengajuan tidak ditemukan.'], 404);
        }

        return response()->json([
            'dispute_id' => $dispute->id,
            'type' => $dispute->type,
            'status' => $dispute->status,
            'created_at' => $dispute->created_at,
            'resolved_at' => $dispute->resolved_at,
            'resolution_note' => $dispute->status !== 'pending' ? $dispute->resolution_note : null,
        ]);
    }

    /**
     * Riwayat dispute untuk 1 entity (API_CONTRACT.md Keputusan #7) —
     * publik, lewat VIEW entity_disputes_public (lihat DisputeService).
     */
    public function historyForEntity(string $entityId)
    {
        return response()->json([
            'data' => $this->disputeService->getPublicHistoryForDisputable('entity', $entityId),
        ]);
    }

    public function approve(string $id, ResolveDisputeRequest $request)
    {
        try {
            $dispute = $this->disputeService->approve($id, $request->user(), $request->validated('note'));
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Dispute disetujui.', 'status' => $dispute->status]);
    }

    public function reject(string $id, ResolveDisputeRequest $request)
    {
        try {
            $dispute = $this->disputeService->reject($id, $request->user(), $request->validated('note'));
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Dispute ditolak.', 'status' => $dispute->status]);
    }
}