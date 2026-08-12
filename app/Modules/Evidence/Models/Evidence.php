<?php

namespace App\Modules\Evidence\Models;

use App\Modules\TenantRegion\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evidence extends Model
{
    use HasUuids;

    protected $table = 'evidences';

    protected $fillable = [
        'source_id',
        'tenant_id',
        'excerpt',
        'locator',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}