<?php

namespace App\Modules\TenantRegion\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tenant extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'status',
    ];

    public function regions(): BelongsToMany
    {
        return $this->belongsToMany(Region::class, 'tenant_region_access')
            ->withPivot('id', 'access_level')
            ->withTimestamps();
    }
}