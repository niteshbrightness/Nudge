<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['tenant_id', 'service', 'credentials', 'meta', 'is_active'])]
class Integration extends Model
{
    use BelongsToTenant;

    protected $casts = [
        'credentials' => 'encrypted:array',
        'meta' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Integration $integration): void {
            $meta = $integration->meta ?? [];
            if (empty($meta['webhook_token'])) {
                $meta['webhook_token'] = (string) Str::uuid();
                $integration->meta = $meta;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
