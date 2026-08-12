<?php

namespace App\Modules\TenantRegion\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantRegionAccess extends Model
{
    use HasUuids;

    protected $table = 'tenant_region_access';

    protected $fillable = [
        'tenant_id',
        'region_id',
        'access_level',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}