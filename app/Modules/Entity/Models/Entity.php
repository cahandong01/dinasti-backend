<?php

namespace App\Modules\Entity\Models;

use App\Modules\Relationship\Models\Relationship;
use App\Modules\TenantRegion\Models\Region;
use App\Modules\TenantRegion\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Entity extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'region_id',
        'type',
        'name',
        'status',
        'first_published_at',
        'slug',
    ];

    protected $casts = [
        'first_published_at' => 'datetime',
    ];

    /**
     * Slug (API_CONTRACT.md Keputusan #1) di-generate otomatis dari
     * nama kalau tidak dikirim eksplisit — satu tempat, konsisten,
     * dipakai baik dari EntityCreateController maupun seeder/tinker.
     * Unik GLOBAL (bukan per-tenant), karena endpoint detail publik.
     */
    protected static function booted(): void
    {
        static::creating(function (Entity $entity) {
            if (empty($entity->slug)) {
                $entity->slug = self::generateUniqueSlug($entity->name);
            }
        });
    }

    private static function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'entitas';
        $slug = $baseSlug;
        $counter = 2;

        while (self::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(EntityAttribute::class);
    }

    public function relationshipsAsSource(): HasMany
    {
        return $this->hasMany(Relationship::class, 'source_entity_id');
    }

    public function relationshipsAsTarget(): HasMany
    {
        return $this->hasMany(Relationship::class, 'target_entity_id');
    }
}