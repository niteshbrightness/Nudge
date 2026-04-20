<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['phone_number', 'client_id', 'tenant_id', 'sms_content', 'action', 'method'])]
class SmsConsentLog extends Model
{
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
