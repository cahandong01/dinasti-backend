<?php

namespace App\Modules\Entity\Models;

use App\Modules\Evidence\Models\Evidence;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityAttribute extends Model
{
    use HasUuids;

    protected $fillable = [
        'entity_id',
        'evidence_id',
        'attribute_key',
        'attribute_value',
        'valid_from',
        'valid_until',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (EntityAttribute $entityAttribute) {
            if (empty($entityAttribute->tenant_id) && $entityAttribute->entity_id) {
                $entityAttribute->tenant_id = Entity::find($entityAttribute->entity_id)?->tenant_id;
            }
        });
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class);
    }
}