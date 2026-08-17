<?php

namespace App\Modules\TenantRegion\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TenantRegion\Requests\AcceptInviteRequest;
use App\Modules\TenantRegion\Requests\CreateInviteRequest;
use App\Modules\TenantRegion\Services\InviteService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InviteController extends Controller
{
    public function __construct(
        private readonly InviteService $inviteService
    ) {}

    public function store(CreateInviteRequest $request)
    {
        $tenantId = $request->header('X-Tenant-ID');

        $invite = $this->inviteService->create(
            $tenantId,
            $request->validated('email'),
            $request->validated('role'),
            $request->user()->id
        );

        return response()->json([
            'message' => 'Undangan dibuat, menunggu approval SUPER_ADMIN lain (bukan situ sendiri) sebelum bisa dipakai.',
            'invite_id' => $invite->id,
            'invite_token' => $invite->token,
            'status' => $invite->status,
            'expires_at' => $invite->expires_at,
        ], 201);
    }

    public function approve(string $id, Request $request)
    {
        try {
            $invite = $this->inviteService->approve($id, $request->user()->id);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Undangan disetujui.', 'status' => $invite->status]);
    }

    public function reject(string $id, Request $request)
    {
        try {
            $invite = $this->inviteService->reject($id, $request->user()->id);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Undangan ditolak.', 'status' => $invite->status]);
    }

    public function accept(string $token, AcceptInviteRequest $request)
    {
        try {
            $user = $this->inviteService->accept(
                $token,
                $request->validated('name'),
                $request->validated('username'),
                $request->validated('password')
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $tokenResult = $user->createToken('api-token');

        return response()->json([
            'message' => 'Akun berhasil dibuat.',
            'user' => $user->only(['id', 'name', 'username', 'email']),
            'token' => $tokenResult->plainTextToken,
        ], 201);
    }
}