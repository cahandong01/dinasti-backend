<?php

namespace App\Modules\Entity\Services;

use App\Modules\Entity\Models\Entity;
use Illuminate\Validation\ValidationException;

class EntityUpdateService
{
    /**
     * Edit entity HANYA boleh kalau statusnya 'draft' atau 'needs_revision'
     * (riset: pola standar CMS/governance — konten yang lagi direview atau
     * sudah published itu "terkunci", tidak bisa diedit langsung).
     */
    private const STATUS_BOLEH_EDIT = ['draft', 'needs_revision'];

    public function update(Entity $entity, array $data): Entity
    {
        if (! in_array($entity->status, self::STATUS_BOLEH_EDIT, true)) {
            throw ValidationException::withMessages([
                'status' => "Entity dengan status '{$entity->status}' tidak bisa diedit langsung. Gunakan alur review/revisi yang sesuai.",
            ]);
        }

        if (isset($data['region_id']) && $data['region_id'] !== $entity->region_id) {
            $punyaAkses = $entity->tenant->regions()->where('regions.id', $data['region_id'])->exists();

            if (! $punyaAkses) {
                throw ValidationException::withMessages([
                    'region_id' => 'Tenant ini tidak punya akses ke region yang dipilih.',
                ]);
            }
        }

        $entity->update($data);

        return $entity->refresh();
    }
}