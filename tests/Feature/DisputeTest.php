<?php

use App\Models\User;
use App\Modules\Entity\Models\Entity;
use App\Modules\TenantRegion\Models\Region;
use App\Modules\TenantRegion\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function siapkanEntityPublishedUntukDispute(): array
{
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    DB::statement("SET app.current_tenant = '{$tenant->id}'");
    $region = Region::create(['name' => 'Banten', 'code' => 'ID-BT', 'level' => 'province']);
    $entity = Entity::create([
        'tenant_id' => $tenant->id,
        'region_id' => $region->id,
        'type' => 'person',
        'name' => 'Budi Published',
        'status' => 'published',
        'first_published_at' => now(),
    ]);

    return [$tenant, $entity];
}

function loginSebagaiLegalReviewerUntukDispute(string $tenantId): User
{
    Role::firstOrCreate(['name' => 'LEGAL_REVIEWER', 'guard_name' => 'web', 'tenant_id' => $tenantId]);
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    setPermissionsTeamId($tenantId);
    $user->assignRole('LEGAL_REVIEWER');

    return $user;
}

$dataDisputeValid = fn (string $entityId) => [
    'type' => 'hak_jawab',
    'disputable_type' => 'entity',
    'disputable_id' => $entityId,
    'name' => 'Budi Pelapor',
    'email' => 'budi@example.com',
    'phone' => '081234567890',
    'disputed_part' => 'Bagian jabatan',
    'supporting_evidence' => 'Surat keterangan resmi dari instansi X.',
    'response_content' => 'Data jabatan yang tercantum sudah tidak berlaku sejak tahun lalu.',
    'is_self_reported' => true,
];

test('publik bisa ajukan dispute TANPA login ke entity published', function () use ($dataDisputeValid) {
    [$tenant, $entity] = siapkanEntityPublishedUntukDispute();
    DB::statement("RESET app.current_tenant"); // simulasi request publik beneran

    $response = $this->postJson('/api/disputes', $dataDisputeValid($entity->id));

    $response->assertStatus(201);
    $response->assertJsonPath('status', 'pending');
});

test('dispute ke entity yang BUKAN published ditolak', function () use ($dataDisputeValid) {
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    DB::statement("SET app.current_tenant = '{$tenant->id}'");
    $region = Region::create(['name' => 'Banten', 'code' => 'ID-BT', 'level' => 'province']);
    $entity = Entity::create([
        'tenant_id' => $tenant->id,
        'region_id' => $region->id,
        'type' => 'person',
        'name' => 'Budi Draft',
        'status' => 'draft',
    ]);
    DB::statement("RESET app.current_tenant");

    $response = $this->postJson('/api/disputes', $dataDisputeValid($entity->id));

    $response->assertStatus(422);
});

test('dispute ditolak kalau sudah lewat 2 bulan sejak first_published_at', function () use ($dataDisputeValid) {
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    DB::statement("SET app.current_tenant = '{$tenant->id}'");
    $region = Region::create(['name' => 'Banten', 'code' => 'ID-BT', 'level' => 'province']);
    $entity = Entity::create([
        'tenant_id' => $tenant->id,
        'region_id' => $region->id,
        'type' => 'person',
        'name' => 'Budi Lama',
        'status' => 'published',
        'first_published_at' => now()->subMonths(3),
    ]);
    DB::statement("RESET app.current_tenant");

    $response = $this->postJson('/api/disputes', $dataDisputeValid($entity->id));

    $response->assertStatus(422);
});

test('koreksi TIDAK terikat batas 2 bulan (beda dari hak_jawab)', function () use ($dataDisputeValid) {
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    DB::statement("SET app.current_tenant = '{$tenant->id}'");
    $region = Region::create(['name' => 'Banten', 'code' => 'ID-BT', 'level' => 'province']);
    $entity = Entity::create([
        'tenant_id' => $tenant->id,
        'region_id' => $region->id,
        'type' => 'person',
        'name' => 'Budi Lama Sekali',
        'status' => 'published',
        'first_published_at' => now()->subMonths(6), // jauh lebih dari 2 bulan
    ]);
    DB::statement("RESET app.current_tenant");

    $payload = $dataDisputeValid($entity->id);
    $payload['type'] = 'koreksi';

    $response = $this->postJson('/api/disputes', $payload);

    $response->assertStatus(201); // TETAP diterima, beda dari hak_jawab
    $response->assertJsonPath('type', 'koreksi');
});

test('supporting_evidence dan response_content WAJIB diisi', function () {
    [$tenant, $entity] = siapkanEntityPublishedUntukDispute();
    DB::statement("RESET app.current_tenant");

    $response = $this->postJson('/api/disputes', [
        'disputable_type' => 'entity',
        'disputable_id' => $entity->id,
        'name' => 'Budi Pelapor',
        'email' => 'budi@example.com',
        'phone' => '081234567890',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['supporting_evidence', 'response_content']);
});

test('LEGAL_REVIEWER approve dispute: entity pindah ke needs_revision', function () use ($dataDisputeValid) {
    [$tenant, $entity] = siapkanEntityPublishedUntukDispute();
    DB::statement("RESET app.current_tenant");
    $disputeResponse = $this->postJson('/api/disputes', $dataDisputeValid($entity->id));
    $disputeId = $disputeResponse->json('dispute_id');

    loginSebagaiLegalReviewerUntukDispute($tenant->id);
    $response = $this->patchJson("/api/disputes/{$disputeId}/approve", ['note' => 'Terbukti valid.'], [
        'X-Tenant-ID' => $tenant->id,
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('status', 'resolved_accepted');
    expect($entity->fresh()->status)->toBe('needs_revision');
});

test('LEGAL_REVIEWER reject dispute: entity TIDAK berubah status', function () use ($dataDisputeValid) {
    [$tenant, $entity] = siapkanEntityPublishedUntukDispute();
    DB::statement("RESET app.current_tenant");
    $disputeResponse = $this->postJson('/api/disputes', $dataDisputeValid($entity->id));
    $disputeId = $disputeResponse->json('dispute_id');

    loginSebagaiLegalReviewerUntukDispute($tenant->id);
    $response = $this->patchJson("/api/disputes/{$disputeId}/reject", ['note' => 'Tidak cukup bukti.'], [
        'X-Tenant-ID' => $tenant->id,
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('status', 'resolved_rejected');
    expect($entity->fresh()->status)->toBe('published');
});

test('RESEARCHER TIDAK BISA approve dispute (cuma LEGAL_REVIEWER)', function () use ($dataDisputeValid) {
    [$tenant, $entity] = siapkanEntityPublishedUntukDispute();
    DB::statement("RESET app.current_tenant");
    $disputeResponse = $this->postJson('/api/disputes', $dataDisputeValid($entity->id));
    $disputeId = $disputeResponse->json('dispute_id');

    DB::statement("SET app.current_tenant = '{$tenant->id}'");
    Role::firstOrCreate(['name' => 'RESEARCHER', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    setPermissionsTeamId($tenant->id);
    $user->assignRole('RESEARCHER');

    $response = $this->patchJson("/api/disputes/{$disputeId}/approve", [], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(403);
});

test('dispute yang sudah resolved TIDAK BISA di-resolve lagi', function () use ($dataDisputeValid) {
    [$tenant, $entity] = siapkanEntityPublishedUntukDispute();
    DB::statement("RESET app.current_tenant");
    $disputeResponse = $this->postJson('/api/disputes', $dataDisputeValid($entity->id));
    $disputeId = $disputeResponse->json('dispute_id');

    loginSebagaiLegalReviewerUntukDispute($tenant->id);
    $this->patchJson("/api/disputes/{$disputeId}/reject", [], ['X-Tenant-ID' => $tenant->id]);

    $response = $this->patchJson("/api/disputes/{$disputeId}/approve", [], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(422);
});