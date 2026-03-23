<?php

use App\Jobs\SendClientNotificationJob;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\Timezone;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $tenant = Tenant::create(['id' => 'test-tenant', 'name' => 'Test Tenant']);
    tenancy()->initialize($tenant);
});

afterEach(function () {
    tenancy()->end();
});

test('dispatches job for client whose local time matches a notification slot', function () {
    Queue::fake();

    // UTC+0 client at 09:00 UTC — matches 09:00 slot
    $tz = Timezone::create([
        'name' => 'UTC',
        'label' => 'UTC (UTC+00:00)',
        'offset' => '+00:00',
        'offset_minutes' => 0,
    ]);

    $client = Client::factory()->create(['timezone_id' => $tz->id]);

    Carbon::setTestNow(Carbon::parse('09:00', 'UTC'));
    config(['notifications.slots' => ['09:00']]);

    $this->artisan('nudge:send-notifications')
        ->expectsOutput('Dispatched notifications for 1 client(s).')
        ->assertExitCode(0);

    Queue::assertPushed(SendClientNotificationJob::class, fn ($job) => $job->client->id === $client->id);

    Carbon::setTestNow();
});

test('does not dispatch job when client local time does not match any slot', function () {
    Queue::fake();

    $tz = Timezone::create([
        'name' => 'UTC',
        'label' => 'UTC (UTC+00:00)',
        'offset' => '+00:00',
        'offset_minutes' => 0,
    ]);

    Client::factory()->create(['timezone_id' => $tz->id]);

    // 12:00 UTC — no match for 09:00 slot
    Carbon::setTestNow(Carbon::parse('12:00', 'UTC'));
    config(['notifications.slots' => ['09:00']]);

    $this->artisan('nudge:send-notifications')
        ->expectsOutput('Dispatched notifications for 0 client(s).')
        ->assertExitCode(0);

    Queue::assertNothingPushed();

    Carbon::setTestNow();
});

test('respects client timezone when matching slots', function () {
    Queue::fake();

    // UTC+5:30 (Asia/Kolkata) — at 03:30 UTC it is 09:00 local
    $tz = Timezone::create([
        'name' => 'Asia/Kolkata',
        'label' => 'IST (UTC+05:30)',
        'offset' => '+05:30',
        'offset_minutes' => 330,
    ]);

    $client = Client::factory()->create(['timezone_id' => $tz->id]);

    Carbon::setTestNow(Carbon::parse('03:30', 'UTC'));
    config(['notifications.slots' => ['09:00']]);

    $this->artisan('nudge:send-notifications')
        ->assertExitCode(0);

    Queue::assertPushed(SendClientNotificationJob::class, fn ($job) => $job->client->id === $client->id);

    Carbon::setTestNow();
});

test('warns and exits when no slots are configured', function () {
    Queue::fake();

    config(['notifications.slots' => []]);

    $this->artisan('nudge:send-notifications')
        ->expectsOutput('No notification slots configured. Set NOTIFICATION_SLOTS in .env')
        ->assertExitCode(0);

    Queue::assertNothingPushed();
});

test('dispatches jobs for multiple clients in matching timezone', function () {
    Queue::fake();

    $tz = Timezone::create([
        'name' => 'UTC',
        'label' => 'UTC (UTC+00:00)',
        'offset' => '+00:00',
        'offset_minutes' => 0,
    ]);

    Client::factory()->count(3)->create(['timezone_id' => $tz->id]);

    Carbon::setTestNow(Carbon::parse('09:00', 'UTC'));
    config(['notifications.slots' => ['09:00']]);

    $this->artisan('nudge:send-notifications')
        ->expectsOutput('Dispatched notifications for 3 client(s).')
        ->assertExitCode(0);

    Queue::assertPushed(SendClientNotificationJob::class, 3);

    Carbon::setTestNow();
});
