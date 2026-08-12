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
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

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