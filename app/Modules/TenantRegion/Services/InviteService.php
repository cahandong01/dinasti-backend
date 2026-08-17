<?php

namespace App\Modules\TenantRegion\Services;

use App\Models\User;
use App\Modules\TenantRegion\Models\Invite;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class InviteService
{
    private const EXPIRES_IN_DAYS = 3;

    /**
     * SEMUA invite mulai dari pending_approval — TIDAK ADA jalur
     * auto-approve, termasuk buat SUPER_ADMIN. Maker-checker (four-eyes
     * principle) WAJIB ditegakkan di kode, bukan cuma di level role,
     * supaya 1 akun yang disusupi tidak bisa bikin+approve sendirian.
     * Ini kontrol SISTEMIK (bukan cuma prosedural) — riset mengonfirmasi
     * regulator/auditor cuma menganggap sah penegakan sistemik yang
     * tidak bisa dilanggar oleh level izin operator manapun, termasuk
     * admin tertinggi.
     */
    public function create(
        string $tenantId,
        string $email,
        string $role,
        string $invitedById
    ): Invite {
        return Invite::create([
            'tenant_id' => $tenantId,
            'email' => $email,
            'role' => $role,
            'token' => Str::random(40),
            'invited_by' => $invitedById,
            'status' => Invite::STATUS_PENDING_APPROVAL,
            'expires_at' => now()->addDays(self::EXPIRES_IN_DAYS),
        ]);
    }

    /**
     * No-self-approval WAJIB ditegakkan di kode (bukan cuma role check)
     * — riset: ini prinsip inti Maker-Checker, ditegakkan di level kode
     * supaya tidak bisa dilanggar walau role-nya secara teknis boleh.
     */
    public function approve(string $inviteId, string $approvedById): Invite
    {
        $invite = Invite::findOrFail($inviteId);

        if (! $invite->isPendingApproval()) {
            throw ValidationException::withMessages([
                'status' => 'Invite ini bukan status pending_approval (mungkin sudah diproses).',
            ]);
        }

        if ($invite->invited_by === $approvedById) {
            throw ValidationException::withMessages([
                'status' => 'Tidak boleh approve undangan yang situ buat sendiri (four-eyes principle).',
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

        if ($invite->invited_by === $approvedById) {
            throw ValidationException::withMessages([
                'status' => 'Tidak boleh reject undangan yang situ buat sendiri (four-eyes principle).',
            ]);
        }

        $invite->update([
            'status' => Invite::STATUS_REJECTED,
            'approved_by' => $approvedById,
            'approved_at' => now(),
        ]);

        return $invite;
    }

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
                'token' => 'Undangan ini belum disetujui. Coba lagi nanti.',
            ]);
        }

        $user = User::create([
            'name' => $name,
            'username' => $username,
            'email' => $invite->email,
            'password' => $password,
        ]);

        setPermissionsTeamId($invite->tenant_id);

        // WAJIB pastikan role-nya ada dulu di tenant ini sebelum assign —
        // JANGAN asumsi role sudah ada (insiden: RoleDoesNotExist exception
        // nyembul jadi 500 kalau diasumsikan tanpa dicek).
        Role::firstOrCreate([
            'name' => $invite->role,
            'guard_name' => 'web',
            'tenant_id' => $invite->tenant_id,
        ]);

        $user->assignRole($invite->role);

        $invite->update(['accepted_at' => now()]);

        return $user;
    }
}