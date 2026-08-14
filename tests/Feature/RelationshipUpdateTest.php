<?php

use App\Models\User;
use App\Modules\Entity\Models\Entity;
use App\Modules\Evidence\Models\Evidence;
use App\Modules\Evidence\Models\Source;
use App\Modules\Relationship\Models\Relationship;
use App\Modules\TenantRegion\Models\Region;
use App\Modules\TenantRegion\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function siapkanRelationshipUntukUpdate(string $status = 'draft'): array
{
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
    aktifkanTenantContext($tenant->id);
    $region = Region::create(['name' => 'Banten', 'code' => 'ID-BT', 'level' => 'province']);

    $orang = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'person', 'name' => 'Budi Santoso']);
    $perusahaan = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $region->id, 'type' => 'company', 'name' => 'PT Sejahtera']);
    $source = Source::create(['tenant_id' => $tenant->id, 'name' => 'Akta Perusahaan', 'type' => 'company_record']);
    $evidence = Evidence::create(['source_id' => $source->id, 'tenant_id' => $tenant->id, 'excerpt' => 'Budi adalah direktur']);

    $relationship = Relationship::create([
        'source_entity_id' => $orang->id,
        'target_entity_id' => $perusahaan->id,
        'evidence_id' => $evidence->id,
        'type' => 'directorship',
        'status' => $status,
    ]);

    Role::firstOrCreate(['name' => 'RESEARCHER', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    setPermissionsTeamId($tenant->id);
    $user->assignRole('RESEARCHER');

    return [$tenant, $relationship, $orang, $perusahaan];
}

test('relationship berstatus draft bisa diedit', function () {
    [$tenant, $relationship] = siapkanRelationshipUntukUpdate('draft');

    $response = $this->patchJson("/api/relationships/{$relationship->id}", [
        'type' => 'shareholder',
    ], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(200);
    $response->assertJsonPath('type', 'shareholder');
});

test('relationship berstatus published TIDAK BISA diedit langsung', function () {
    [$tenant, $relationship] = siapkanRelationshipUntukUpdate('published');

    $response = $this->patchJson("/api/relationships/{$relationship->id}", [
        'type' => 'shareholder',
    ], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(422);
    expect($relationship->refresh()->type)->toBe('directorship');
});

test('mencoba ganti source_entity_id lewat update diabaikan (bukan field yang boleh diedit)', function () {
    [$tenant, $relationship, $orang, $perusahaan] = siapkanRelationshipUntukUpdate('draft');
    $entityLain = Entity::create(['tenant_id' => $tenant->id, 'region_id' => $perusahaan->region_id, 'type' => 'person', 'name' => 'Orang Lain']);

    $response = $this->patchJson("/api/relationships/{$relationship->id}", [
        'source_entity_id' => $entityLain->id,
        'type' => 'shareholder',
    ], ['X-Tenant-ID' => $tenant->id]);

    $response->assertStatus(200);
    expect($relationship->refresh()->source_entity_id)->toBe($orang->id); // tetap yang lama, gak ikut ganti
});