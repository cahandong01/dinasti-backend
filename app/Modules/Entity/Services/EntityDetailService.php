<?php

namespace App\Modules\Entity\Services;

use App\Modules\Entity\Models\Entity;

class EntityDetailService
{
    /**
     * Ambil entity lengkap: atribut (bitemporal, D6), evidence trail di
     * tiap atribut/relationship, dan relationship dua arah (source & target).
     * Isolasi tenant otomatis ditangani RLS (D10) — tidak perlu filter manual.
     */
        public function getDetail(string $slug): ?Entity
    {
        return Entity::with([
            'attributes.evidence.source',
            'relationshipsAsSource.evidence.source',
            'relationshipsAsSource.targetEntity',
            'relationshipsAsTarget.evidence.source',
            'relationshipsAsTarget.sourceEntity',
        ])->where('slug', $slug)->first();
    }
}