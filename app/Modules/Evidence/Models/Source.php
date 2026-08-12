<?php

namespace App\Modules\Evidence\Models;

use App\Modules\TenantRegion\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'url',
        'reliability',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'date',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }
}