<?php

namespace App\Modules\Dispute\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Dispute extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_RESOLVED_ACCEPTED = 'resolved_accepted';
    public const STATUS_RESOLVED_REJECTED = 'resolved_rejected';

    protected $fillable = [
        'tenant_id',
        'disputable_type',
        'disputable_id',
        'name',
        'email',
        'phone',
        'disputed_part',
        'supporting_evidence',
        'response_content',
        'status',
        'resolved_by',
        'resolved_at',
        'resolution_note',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function disputable(): MorphTo
    {
        return $this->morphTo();
    }
}