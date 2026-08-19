<?php

use App\Modules\Dispute\Models\Dispute;
use App\Modules\Entity\Models\Entity;
use App\Modules\TenantRegion\Models\Region;
use App\Modules\TenantRegion\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('riwayat dispute resolved muncul publik TANPA data pribadi pelapor', function () {
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

    Dispute::create([
        'tenant_id' => $tenant->id,
        'disputable_type' => 'entity',
        'disputable_id' => $entity->id,
        'type' => 'koreksi',
        'tracking_token' => 'token-rahasia-123',
        'name' => 'Budi Pelapor Rahasia',
        'email' => 'rahasia@example.com',
        'phone' => '081234567890',
        'supporting_evidence' => 'Bukti',
        'response_content' => 'Data yang tercantum sudah tidak berlaku.',
        'status' => Dispute::STATUS_RESOLVED_ACCEPTED,
        'resolved_at' => now(),
        'resolution_note' => 'Terbukti valid.',
    ]);

    DB::statement("RESET app.current_tenant"); // simulasi request publik beneran

    $response = $this->getJson("/api/entities/{$entity->id}/disputes");

    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['response_content'])->toBe('Data yang tercantum sudah tidak berlaku.');

    // Field PII TIDAK BOLEH ada sama sekali di response
    expect($data[0])->not->toHaveKey('name');
    expect($data[0])->not->toHaveKey('email');
    expect($data[0])->not->toHaveKey('phone');
    expect($data[0])->not->toHaveKey('tracking_token');
});

test('dispute yang masih pending TIDAK muncul di riwayat publik', function () {
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

    Dispute::create([
        'tenant_id' => $tenant->id,
        'disputable_type' => 'entity',
        'disputable_id' => $entity->id,
        'type' => 'koreksi',
        'tracking_token' => 'token-pending-456',
        'name' => 'Budi Pelapor',
        'email' => 'budi@example.com',
        'phone' => '081234567890',
        'supporting_evidence' => 'Bukti',
        'response_content' => 'Masih menunggu review.',
        'status' => Dispute::STATUS_PENDING,
    ]);

    DB::statement("RESET app.current_tenant");

    $response = $this->getJson("/api/entities/{$entity->id}/disputes");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(0);
});