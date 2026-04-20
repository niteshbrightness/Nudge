<?php

use App\Models\Client;
use App\Models\SmsConsentLog;
use App\Models\Tenant;
use App\Models\Timezone;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $tenant = Tenant::create(['id' => 'test-tenant', 'name' => 'Test Tenant']);
    tenancy()->initialize($tenant);

    Timezone::create([
        'name' => 'UTC',
        'label' => 'UTC (UTC+00:00)',
        'offset' => '+00:00',
        'offset_minutes' => 0,
    ]);

    config(['notifications.twilio.auth_token' => 'test-auth-token']);
});

afterEach(function () {
    tenancy()->end();
});

function twilioSignature(string $url, array $params, string $authToken): string
{
    $validationString = $url;

    if (! empty($params)) {
        ksort($params);
        foreach ($params as $key => $value) {
            $validationString .= $key.$value;
        }
    }

    return base64_encode(hash_hmac('sha1', $validationString, $authToken, true));
}

describe('TwilioInboundWebhookController', function () {
    it('rejects requests with an invalid signature', function () {
        $this->postJson('/webhook/twilio/inbound', ['From' => '+15550000001', 'Body' => 'STOP'], [
            'X-Twilio-Signature' => 'invalid-signature',
        ])->assertForbidden();
    });

    it('rejects requests when auth token is not configured', function () {
        config(['notifications.twilio.auth_token' => '']);

        $this->postJson('/webhook/twilio/inbound', ['From' => '+15550000001', 'Body' => 'STOP'], [
            'X-Twilio-Signature' => 'any',
        ])->assertForbidden();
    });

    it('opts out a client when STOP is received', function () {
        $client = Client::factory()->create([
            'phone' => '+15550000001',
            'sms_consent' => true,
        ]);

        $params = ['From' => '+15550000001', 'Body' => 'STOP'];
        $url = route('webhook.twilio.inbound');
        $signature = twilioSignature($url, $params, 'test-auth-token');

        $this->post('/webhook/twilio/inbound', $params, ['X-Twilio-Signature' => $signature])
            ->assertOk()
            ->assertHeader('Content-Type', 'text/xml; charset=UTF-8');

        expect($client->fresh()->sms_consent)->toBeFalse();
        expect(SmsConsentLog::where('action', 'stop')->count())->toBe(1);
    });

    it('opts out a client for UNSUBSCRIBE keyword', function () {
        $client = Client::factory()->create(['phone' => '+15550000002', 'sms_consent' => true]);
        $params = ['From' => '+15550000002', 'Body' => 'UNSUBSCRIBE'];
        $url = route('webhook.twilio.inbound');

        $this->post('/webhook/twilio/inbound', $params, [
            'X-Twilio-Signature' => twilioSignature($url, $params, 'test-auth-token'),
        ])->assertOk();

        expect($client->fresh()->sms_consent)->toBeFalse();
    });

    it('opts in a client when START is received', function () {
        $client = Client::factory()->create(['phone' => '+15550000003', 'sms_consent' => false]);
        $params = ['From' => '+15550000003', 'Body' => 'START'];
        $url = route('webhook.twilio.inbound');

        $this->post('/webhook/twilio/inbound', $params, [
            'X-Twilio-Signature' => twilioSignature($url, $params, 'test-auth-token'),
        ])->assertOk();

        expect($client->fresh()->sms_consent)->toBeTrue();
        expect(SmsConsentLog::where('action', 'start')->count())->toBe(1);
    });

    it('returns empty TwiML for unknown phone numbers', function () {
        $params = ['From' => '+19990000000', 'Body' => 'STOP'];
        $url = route('webhook.twilio.inbound');

        $this->post('/webhook/twilio/inbound', $params, [
            'X-Twilio-Signature' => twilioSignature($url, $params, 'test-auth-token'),
        ])->assertOk();

        expect(SmsConsentLog::count())->toBe(0);
    });

    it('opts out matching clients across multiple tenants', function () {
        tenancy()->end();

        $tenant2 = Tenant::create(['id' => 'tenant-2', 'name' => 'Tenant 2']);

        tenancy()->initialize($tenant2);
        Timezone::firstOrCreate(['name' => 'UTC'], ['label' => 'UTC (UTC+00:00)', 'offset' => '+00:00', 'offset_minutes' => 0]);
        $clientTenant2 = Client::factory()->create(['phone' => '+15550000004', 'sms_consent' => true]);
        tenancy()->end();

        $tenant1 = Tenant::find('test-tenant');
        tenancy()->initialize($tenant1);
        $clientTenant1 = Client::factory()->create(['phone' => '+15550000004', 'sms_consent' => true]);

        $params = ['From' => '+15550000004', 'Body' => 'STOP'];
        $url = route('webhook.twilio.inbound');

        $this->post('/webhook/twilio/inbound', $params, [
            'X-Twilio-Signature' => twilioSignature($url, $params, 'test-auth-token'),
        ])->assertOk();

        expect($clientTenant1->fresh()->sms_consent)->toBeFalse();
        expect($clientTenant2->fresh()->sms_consent)->toBeFalse();
    });
});
