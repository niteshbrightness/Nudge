<?php

namespace App\Models;

use Database\Factories\WebhookEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['tenant_id', 'project_id', 'event_type', 'raw_payload', 'parsed_data', 'activecollab_url', 'short_url', 'received_at'])]
class WebhookEvent extends Model
{
    /** @use HasFactory<WebhookEventFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'raw_payload' => AsArrayObject::class,
            'parsed_data' => AsArrayObject::class,
            'received_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
