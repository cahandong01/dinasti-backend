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

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class);
    }
}