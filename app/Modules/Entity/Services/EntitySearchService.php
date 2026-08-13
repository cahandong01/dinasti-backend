<?php

namespace App\Modules\Entity\Services;

use App\Modules\Entity\Models\Entity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EntitySearchService
{
    /**
     * Cari entity pakai fuzzy-match nama (pg_trgm, D2), diurutkan
     * dari yang paling mirip. Isolasi tenant otomatis ditangani RLS
     * (D10) — service ini TIDAK perlu filter tenant_id manual.
     */
    public function search(string $query, ?string $type = null, int $perPage = 15): LengthAwarePaginator
    {
        return Entity::query()
            ->select('*')
            ->selectRaw('similarity(name, ?) AS relevance', [$query])
            ->whereRaw('name % ?', [$query])
            ->when($type, fn ($builder) => $builder->where('type', $type))
            ->orderByDesc('relevance')
            ->paginate($perPage)
            ->appends(['q' => $query, 'type' => $type]);
    }
}