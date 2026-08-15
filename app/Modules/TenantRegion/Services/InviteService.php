<?php

namespace App\Modules\TenantRegion\Services;

use App\Models\User;
use App\Modules\TenantRegion\Models\Invite;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteService
{
    private const EXPIRES_IN_DAYS = 3;

    /**
     * $inviterIsSuperAdmin menentukan status awal invite:
     * - SUPER_ADMIN mengundang -> langsung approved (otoritas tertinggi).
     * - TENANT_ADMIN mengundang -> pending_approval, WAJIB di-approve
     *   SUPER_ADMIN lain dulu (maker-checker / four-eyes principle).
     */
    public function create(
        string $tenantId,
        string $email,
        string $role,
        string $invitedById,
        bool $inviterIsSuperAdmin
    ): Invite {
        return Invite::create([
            'tenant_id' => $tenantId,
            'email' => $email,
            'role' => $role,
            'token' => Str::random(40),
            'invited_by' => $invitedById,
            'status' => $inviterIsSuperAdmin
                ? Invite::STATUS_APPROVED
                : Invite::STATUS_PENDING_APPROVAL,
            'approved_by' => $inviterIsSuperAdmin ? $invitedById : null,
            'approved_at' => $inviterIsSuperAdmin ? now() : null,
            'expires_at' => now()->addDays(self::EXPIRES_IN_DAYS),
        ]);
    }

    public function approve(string $inviteId, string $approvedById): Invite
    {
        $invite = Invite::findOrFail($inviteId);

        if (! $invite->isPendingApproval()) {
            throw ValidationException::withMessages([
                'status' => 'Invite ini bukan status pending_approval (mungkin sudah diproses).',
            ]);
        }

        $invite->update([
            'status' => Invite::STATUS_APPROVED,
            'approved_by' => $approvedById,
            'approved_at' => now(),
        ]);

        return $invite;
    }

    public function reject(string $inviteId, string $approvedById): Invite
    {
        $invite = Invite::findOrFail($inviteId);

        if (! $invite->isPendingApproval()) {
            throw ValidationException::withMessages([
                'status' => 'Invite ini bukan status pending_approval (mungkin sudah diproses).',
            ]);
        }

        $invite->update([
            'status' => Invite::STATUS_REJECTED,
            'approved_by' => $approvedById,
            'approved_at' => now(),
        ]);

        return $invite;
    }

    /**
     * Klaim invite jadi user baru. Password di-hash otomatis lewat
     * cast 'hashed' bawaan model User (Laravel 10+).
     */
    public function accept(string $token, string $name, string $username, string $password): User
    {
        $invite = Invite::where('token', $token)->first();

        if (! $invite) {
            throw ValidationException::withMessages(['token' => 'Undangan tidak ditemukan.']);
        }

        if ($invite->isAccepted()) {
            throw ValidationException::withMessages(['token' => 'Undangan ini sudah diklaim sebelumnya.']);
        }

        if ($invite->isExpired()) {
            throw ValidationException::withMessages(['token' => 'Undangan ini sudah kedaluwarsa.']);
        }

        if (! $invite->isApproved()) {
            throw ValidationException::withMessages([
                'token' => 'Undangan ini belum disetujui SUPER_ADMIN. Coba lagi nanti.',
            ]);
        }

        $user = User::create([
            'name' => $name,
            'username' => $username,
            'email' => $invite->email,
            'password' => $password,
        ]);

        setPermissionsTeamId($invite->tenant_id);
        $user->assignRole($invite->role);

        $invite->update(['accepted_at' => now()]);

        return $user;
    }
}