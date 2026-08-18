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
        'type',
        'tracking_token',
        'name',
        'email',
        'phone',
        'disputed_part',
        'supporting_evidence',
        'response_content',
        'is_self_reported',
        'status',
        'resolved_by',
        'resolved_at',
        'resolution_note',
    ];

    public const TYPE_HAK_JAWAB = 'hak_jawab';
    public const TYPE_KOREKSI = 'koreksi';

    protected $casts = [
        'resolved_at' => 'datetime',
        'is_self_reported' => 'boolean',
    ];

    public function disputable(): MorphTo
    {
        return $this->morphTo();
    }
}