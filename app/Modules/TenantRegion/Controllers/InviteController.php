<?php

namespace App\Modules\TenantRegion\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TenantRegion\Requests\AcceptInviteRequest;
use App\Modules\TenantRegion\Requests\CreateInviteRequest;
use App\Modules\TenantRegion\Services\InviteService;
use Illuminate\Validation\ValidationException;

class InviteController extends Controller
{
    public function __construct(
        private readonly InviteService $inviteService
    ) {}

    public function store(CreateInviteRequest $request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $inviterIsSuperAdmin = $request->user()->hasRole('SUPER_ADMIN');

        $invite = $this->inviteService->create(
            $tenantId,
            $request->validated('email'),
            $request->validated('role'),
            $request->user()->id,
            $inviterIsSuperAdmin
        );

        $message = $inviterIsSuperAdmin
            ? 'Undangan dibuat dan langsung aktif. Kirim link ini ke penerima secara manual.'
            : 'Undangan dibuat, TAPI masih menunggu approval SUPER_ADMIN sebelum bisa dipakai.';

        return response()->json([
            'message' => $message,
            'invite_id' => $invite->id,
            'invite_token' => $invite->token,
            'status' => $invite->status,
            'expires_at' => $invite->expires_at,
        ], 201);
    }

    public function approve(string $id, \Illuminate\Http\Request $request)
    {
        try {
            $invite = $this->inviteService->approve($id, $request->user()->id);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Undangan disetujui.', 'status' => $invite->status]);
    }

    public function reject(string $id, \Illuminate\Http\Request $request)
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