<?php

namespace App\Modules\Entity\Models;

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
        return $this->hasMany(\App\Modules\Entity\Models\EntityAttribute::class);
    }
}