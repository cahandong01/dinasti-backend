<?php

namespace App\Modules\Entity\Models;

use App\Modules\Relationship\Models\Relationship;
use App\Modules\TenantRegion\Models\Region;
use App\Modules\TenantRegion\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entity extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'region_id',
        'type',
        'name',
        'status',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(EntityAttribute::class);
    }

    public function relationshipsAsSource(): HasMany
    {
        return $this->hasMany(Relationship::class, 'source_entity_id');
    }

    public function relationshipsAsTarget(): HasMany
    {
        return $this->hasMany(Relationship::class, 'target_entity_id');
    }
}