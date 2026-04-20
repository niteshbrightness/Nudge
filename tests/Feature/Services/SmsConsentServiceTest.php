<?php

use App\Models\Client;
use App\Models\SmsConsentLog;
use App\Models\Tenant;
use App\Models\Timezone;
use App\Models\User;
use App\Services\SmsConsentService;
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

    $this->service = app(SmsConsentService::class);
    $this->admin = User::factory()->create();
});

afterEach(function () {
    tenancy()->end();
});

describe('grantConsent', function () {
    it('sets sms_consent to true and records sms_consent_given_at', function () {
        $client = Client::factory()->create(['sms_consent' => false]);

        $this->service->grantConsent($client, $this->admin, 'Signed contract on 2026-04-01');

        expect($client->fresh())
            ->sms_consent->toBeTrue()
            ->sms_consent_given_at->not->toBeNull();
    });

    it('creates an sms_consent_logs record with action granted and method admin', function () {
        $client = Client::factory()->create(['sms_consent' => false]);

        $this->service->grantConsent($client, $this->admin, 'Verbal confirmation');

        $log = SmsConsentLog::first();

        expect($log)
            ->phone_number->toBe($client->phone)
            ->client_id->toBe($client->id)
            ->tenant_id->toBe($client->tenant_id)
            ->sms_content->toBe('Verbal confirmation')
            ->action->toBe('granted')
            ->method->toBe('admin');
    });
});

describe('revokeConsent', function () {
    it('sets sms_consent to false', function () {
        $client = Client::factory()->create(['sms_consent' => true]);

        $this->service->revokeConsent($client, $this->admin);

        expect($client->fresh()->sms_consent)->toBeFalse();
    });

    it('creates an sms_consent_logs record with action revoked and method admin', function () {
        $client = Client::factory()->create(['sms_consent' => true]);

        $this->service->revokeConsent($client, $this->admin);

        $log = SmsConsentLog::first();

        expect($log)
            ->action->toBe('revoked')
            ->method->toBe('admin')
            ->sms_content->toBeNull();
    });
});

describe('handleOptOut', function () {
    it('sets sms_consent to false', function () {
        $client = Client::factory()->create(['sms_consent' => true]);

        $this->service->handleOptOut($client, 'STOP', $client->phone);

        expect($client->fresh()->sms_consent)->toBeFalse();
    });

    it('creates an sms_consent_logs record with action stop and method inbound_sms', function () {
        $client = Client::factory()->create(['sms_consent' => true]);

        $this->service->handleOptOut($client, 'STOP please', $client->phone);

        $log = SmsConsentLog::first();

        expect($log)
            ->phone_number->toBe($client->phone)
            ->client_id->toBe($client->id)
            ->sms_content->toBe('STOP please')
            ->action->toBe('stop')
            ->method->toBe('inbound_sms');
    });
});

describe('handleOptIn', function () {
    it('sets sms_consent to true and records sms_consent_given_at', function () {
        $client = Client::factory()->create(['sms_consent' => false]);

        $this->service->handleOptIn($client, 'START', $client->phone);

        expect($client->fresh())
            ->sms_consent->toBeTrue()
            ->sms_consent_given_at->not->toBeNull();
    });

    it('creates an sms_consent_logs record with action start and method inbound_sms', function () {
        $client = Client::factory()->create(['sms_consent' => false]);

        $this->service->handleOptIn($client, 'START', $client->phone);

        $log = SmsConsentLog::first();

        expect($log)
            ->action->toBe('start')
            ->method->toBe('inbound_sms')
            ->sms_content->toBe('START');
    });
});
