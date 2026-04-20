<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['tenant_id', 'name', 'phone', 'timezone_id', 'notes', 'is_active', 'sms_consent', 'sms_consent_given_at'])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sms_consent' => 'boolean',
            'sms_consent_given_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function timezone(): BelongsTo
    {
        return $this->belongsTo(Timezone::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class);
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    public function smsConsentLogs(): HasMany
    {
        return $this->hasMany(SmsConsentLog::class);
    }
}
