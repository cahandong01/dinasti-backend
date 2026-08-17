<?php

namespace App\Modules\Dispute\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dispute\Requests\CreateDisputeRequest;
use App\Modules\Dispute\Requests\ResolveDisputeRequest;
use App\Modules\Dispute\Services\DisputeService;
use Illuminate\Http\Request;
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
                $request->validated('disputable_type'),
                $request->validated('disputable_id'),
                $request->validated('name'),
                $request->validated('email'),
                $request->validated('phone'),
                $request->validated('disputed_part'),
                $request->validated('supporting_evidence'),
                $request->validated('response_content'),
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Pengajuan Hak Jawab diterima, akan ditinjau LEGAL_REVIEWER.',
            'dispute_id' => $dispute->id,
            'status' => $dispute->status,
        ], 201);
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