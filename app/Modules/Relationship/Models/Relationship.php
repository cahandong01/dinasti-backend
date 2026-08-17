<?php

namespace App\Modules\Relationship\Models;

use App\Modules\Entity\Models\Entity;
use App\Modules\Evidence\Models\Evidence;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Relationship extends Model
{
    use HasUuids;

    protected $fillable = [
        'source_entity_id',
        'target_entity_id',
        'evidence_id',
        'type',
        'valid_from',
        'valid_until',
        'status',
        'first_published_at',
    ];

        protected $casts = [
        'valid_from' => 'date',
        'valid_until' => 'date',
        'first_published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Relationship $relationship) {
            if (empty($relationship->tenant_id) && $relationship->source_entity_id) {
                $relationship->tenant_id = Entity::find($relationship->source_entity_id)?->tenant_id;
            }
        });
    }

    public function sourceEntity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'source_entity_id');
    }

    public function targetEntity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'target_entity_id');
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class);
    }
}