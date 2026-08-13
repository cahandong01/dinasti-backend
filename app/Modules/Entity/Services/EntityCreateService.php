<?php

namespace App\Modules\Entity\Services;

use App\Modules\Entity\Models\Entity;
use App\Modules\TenantRegion\Models\Tenant;
use Illuminate\Validation\ValidationException;

class EntityCreateService
{
    /**
     * Bikin entity baru. Status SELALU 'draft' (default database, D7) —
     * tidak menerima status dari request sama sekali, supaya tidak ada
     * jalur untuk skip legal review gate lewat parameter yang dimanipulasi.
     */
    public function create(Tenant $tenant, string $name, string $type, string $regionId): Entity
    {
        $punyaAkses = $tenant->regions()->where('regions.id', $regionId)->exists();

        if (! $punyaAkses) {
            throw ValidationException::withMessages([
                'region_id' => 'Tenant ini tidak punya akses ke region yang dipilih.',
            ]);
        }

        $entity = Entity::create([
            'tenant_id' => $tenant->id,
            'region_id' => $regionId,
            'type' => $type,
            'name' => $name,
            // 'status' SENGAJA tidak diisi — biar database yang tentukan default 'draft'
        ]);

        return $entity->refresh();
    }
}