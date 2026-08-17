<?php

use App\Models\User;
use App\Modules\TenantRegion\Models\Invite;
use App\Modules\TenantRegion\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function siapkanTenantUntukInvite(): Tenant
{
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    aktifkanTenantContext($tenant->id);

    return $tenant;
}

function loginSebagaiUntukInvite(string $roleName, string $tenantId): User
{
    Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web', 'tenant_id' => $tenantId]);
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    setPermissionsTeamId($tenantId);
    $user->assignRole($roleName);

    return $user;
}

function loginSebagaiSuperAdminUntukInvite(): User
{
    Role::firstOrCreate(['name' => 'SUPER_ADMIN', 'guard_name' => 'web', 'tenant_id' => null]);
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    // WAJIB null eksplisit dulu — SUPER_ADMIN itu role GLOBAL, baris
    // model_has_roles-nya juga wajib tenant_id null. Tanpa ini, nilai
    // getPermissionsTeamId() bisa "kebawa" dari state test sebelumnya
    // dalam 1 file run yang sama, bikin pivot ke-assign ke tenant yang
    // salah dan role jadi tidak terdeteksi.
    setPermissionsTeamId(null);
    $user->assignRole('SUPER_ADMIN');

    return $user;
}

test('TENANT_ADMIN bikin invite RESEARCHER, status pending_approval', function () {
    $tenant = siapkanTenantUntukInvite();
    loginSebagaiUntukInvite('TENANT_ADMIN', $tenant->id);

    $response = $this->postJson('/api/invites', [
        'email' => 'calon@example.com',
        'role' => 'RESEARCHER',
    ], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(201);
    $response->assertJsonPath('status', 'pending_approval');
});

test('SUPER_ADMIN bikin invite JUGA tetap pending_approval, tidak ada jalur auto-approve', function () {
    $tenant = siapkanTenantUntukInvite();
    loginSebagaiSuperAdminUntukInvite();

    $response = $this->postJson('/api/invites', [
        'email' => 'calon@example.com',
        'role' => 'RESEARCHER',
    ], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(201);
    $response->assertJsonPath('status', 'pending_approval');
});

test('TENANT_ADMIN TIDAK BOLEH invite orang jadi TENANT_ADMIN', function () {
    $tenant = siapkanTenantUntukInvite();
    loginSebagaiUntukInvite('TENANT_ADMIN', $tenant->id);

    $response = $this->postJson('/api/invites', [
        'email' => 'calon@example.com',
        'role' => 'TENANT_ADMIN',
    ], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(422);
});

test('alur lengkap: TENANT_ADMIN invite -> SUPER_ADMIN approve -> accept sukses', function () {
    $tenant = siapkanTenantUntukInvite();
    loginSebagaiUntukInvite('TENANT_ADMIN', $tenant->id);

    $inviteResponse = $this->postJson('/api/invites', [
        'email' => 'calon@example.com',
        'role' => 'RESEARCHER',
    ], ['X-Tenant-ID' => $tenant->id]);

    $inviteId = $inviteResponse->json('invite_id');
    $token = $inviteResponse->json('invite_token');

    loginSebagaiSuperAdminUntukInvite();
    $approveResponse = $this->patchJson("/api/invites/{$inviteId}/approve", [], ['X-Tenant-ID' => $tenant->id]);
    $approveResponse->assertStatus(200);

    $acceptResponse = $this->postJson("/api/invites/{$token}/accept", [
        'name' => 'Calon Researcher',
        'username' => 'calonresearcher',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $acceptResponse->assertStatus(201);
    $acceptResponse->assertJsonStructure(['user', 'token']);

    // Pastikan role beneran ke-assign
    $userBaru = User::where('username', 'calonresearcher')->first();
    setPermissionsTeamId($tenant->id);
    expect($userBaru->hasRole('RESEARCHER'))->toBeTrue();
});

test('accept invite yang belum di-approve SUPER_ADMIN ditolak', function () {
    $tenant = siapkanTenantUntukInvite();
    loginSebagaiUntukInvite('TENANT_ADMIN', $tenant->id);

    $inviteResponse = $this->postJson('/api/invites', [
        'email' => 'calon@example.com',
        'role' => 'RESEARCHER',
    ], ['X-Tenant-ID' => $tenant->id]);

    $token = $inviteResponse->json('invite_token');

    $acceptResponse = $this->postJson("/api/invites/{$token}/accept", [
        'name' => 'Calon Researcher',
        'username' => 'calonresearcher',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $acceptResponse->assertStatus(422);
});

test('SUPER_ADMIN bisa reject invite pending', function () {
    $tenant = siapkanTenantUntukInvite();
    loginSebagaiUntukInvite('TENANT_ADMIN', $tenant->id);

    $inviteResponse = $this->postJson('/api/invites', [
        'email' => 'calon@example.com',
        'role' => 'RESEARCHER',
    ], ['X-Tenant-ID' => $tenant->id]);

    $inviteId = $inviteResponse->json('invite_id');

    loginSebagaiSuperAdminUntukInvite();
    $rejectResponse = $this->patchJson("/api/invites/{$inviteId}/reject", [], ['X-Tenant-ID' => $tenant->id]);

    $rejectResponse->assertStatus(200);
    $rejectResponse->assertJsonPath('status', 'rejected');
});

test('TENANT_ADMIN TIDAK BOLEH approve invite sendiri (cuma SUPER_ADMIN)', function () {
    $tenant = siapkanTenantUntukInvite();
    $tenantAdmin = loginSebagaiUntukInvite('TENANT_ADMIN', $tenant->id);

    $invite = Invite::create([
        'tenant_id' => $tenant->id,
        'email' => 'calon@example.com',
        'role' => 'RESEARCHER',
        'token' => 'dummy-token',
        'invited_by' => $tenantAdmin->id,
        'status' => Invite::STATUS_PENDING_APPROVAL,
        'expires_at' => now()->addDays(3),
    ]);

    $response = $this->patchJson("/api/invites/{$invite->id}/approve", [], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(403);
});

test('accept invite dengan token salah dibalas 422', function () {
    $response = $this->postJson('/api/invites/token-ngasal/accept', [
        'name' => 'Calon Researcher',
        'username' => 'calonresearcher',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422);
});

test('accept invite yang sudah expired ditolak', function () {
    $tenant = siapkanTenantUntukInvite();
    $superAdmin = loginSebagaiSuperAdminUntukInvite();

    $invite = Invite::create([
        'tenant_id' => $tenant->id,
        'email' => 'calon@example.com',
        'role' => 'RESEARCHER',
        'token' => 'token-expired',
        'invited_by' => $superAdmin->id,
        'status' => Invite::STATUS_APPROVED,
        'approved_by' => $superAdmin->id,
        'approved_at' => now(),
        'expires_at' => now()->subDay(), // sudah lewat
    ]);

    $response = $this->postJson("/api/invites/{$invite->token}/accept", [
        'name' => 'Calon Researcher',
        'username' => 'calonresearcher',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422);
});