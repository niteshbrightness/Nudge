<?php

namespace App\Services;

use App\Models\Client;
use App\Models\SmsConsentLog;
use App\Models\User;

class SmsConsentService
{
    public function grantConsent(Client $client, User $admin, ?string $notes = null): void
    {
        $client->update([
            'sms_consent' => true,
            'sms_consent_given_at' => now(),
        ]);

        SmsConsentLog::create([
            'phone_number' => $client->phone,
            'client_id' => $client->id,
            'tenant_id' => $client->tenant_id,
            'sms_content' => $notes,
            'action' => 'granted',
            'method' => 'admin',
        ]);
    }

    public function revokeConsent(Client $client, User $admin): void
    {
        $client->update(['sms_consent' => false]);

        SmsConsentLog::create([
            'phone_number' => $client->phone,
            'client_id' => $client->id,
            'tenant_id' => $client->tenant_id,
            'sms_content' => null,
            'action' => 'revoked',
            'method' => 'admin',
        ]);
    }

    public function handleOptOut(Client $client, string $messageBody, string $fromNumber): void
    {
        $client->update(['sms_consent' => false]);

        SmsConsentLog::create([
            'phone_number' => $fromNumber,
            'client_id' => $client->id,
            'tenant_id' => $client->tenant_id,
            'sms_content' => $messageBody,
            'action' => 'stop',
            'method' => 'inbound_sms',
        ]);
    }

    public function handleOptIn(Client $client, string $messageBody, string $fromNumber): void
    {
        $client->update([
            'sms_consent' => true,
            'sms_consent_given_at' => now(),
        ]);

        SmsConsentLog::create([
            'phone_number' => $fromNumber,
            'client_id' => $client->id,
            'tenant_id' => $client->tenant_id,
            'sms_content' => $messageBody,
            'action' => 'start',
            'method' => 'inbound_sms',
        ]);
    }
}
