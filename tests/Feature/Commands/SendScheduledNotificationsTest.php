<?php

use App\Jobs\SendClientNotificationJob;
use App\Models\Client;
use App\Models\Project;
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

test('dispatches job for each active project of a client whose local time matches a notification slot', function () {
    Queue::fake();

    $tz = Timezone::create([
        'name' => 'UTC',
        'label' => 'UTC (UTC+00:00)',
        'offset' => '+00:00',
        'offset_minutes' => 0,
    ]);

    $client = Client::factory()->create(['timezone_id' => $tz->id]);
    $projectA = Project::factory()->create(['status' => 'active']);
    $projectB = Project::factory()->create(['status' => 'active']);
    $client->projects()->attach([$projectA->id, $projectB->id]);

    Carbon::setTestNow(Carbon::parse('09:00', 'UTC'));
    config(['notifications.slots' => ['09:00']]);

    $this->artisan('nudge:send-notifications')
        ->expectsOutput('Dispatched 2 notification job(s).')
        ->assertExitCode(0);

    Queue::assertPushed(SendClientNotificationJob::class, 2);

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

    $client = Client::factory()->create(['timezone_id' => $tz->id]);
    $project = Project::factory()->create(['status' => 'active']);
    $client->projects()->attach($project->id);

    // 12:00 UTC — no match for 09:00 slot
    Carbon::setTestNow(Carbon::parse('12:00', 'UTC'));
    config(['notifications.slots' => ['09:00']]);

    $this->artisan('nudge:send-notifications')
        ->expectsOutput('Dispatched 0 notification job(s).')
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
    $project = Project::factory()->create(['status' => 'active']);
    $client->projects()->attach($project->id);

    Carbon::setTestNow(Carbon::parse('03:30', 'UTC'));
    config(['notifications.slots' => ['09:00']]);

    $this->artisan('nudge:send-notifications')
        ->assertExitCode(0);

    Queue::assertPushed(SendClientNotificationJob::class, fn ($job) => $job->client->id === $client->id && $job->project->id === $project->id);

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

test('dispatches one job per client-project pair across multiple clients', function () {
    Queue::fake();

    $tz = Timezone::create([
        'name' => 'UTC',
        'label' => 'UTC (UTC+00:00)',
        'offset' => '+00:00',
        'offset_minutes' => 0,
    ]);

    // 3 clients, each with 1 active project = 3 jobs
    $clients = Client::factory()->count(3)->create(['timezone_id' => $tz->id]);
    foreach ($clients as $client) {
        $project = Project::factory()->create(['status' => 'active']);
        $client->projects()->attach($project->id);
    }

    Carbon::setTestNow(Carbon::parse('09:00', 'UTC'));
    config(['notifications.slots' => ['09:00']]);

    $this->artisan('nudge:send-notifications')
        ->expectsOutput('Dispatched 3 notification job(s).')
        ->assertExitCode(0);

    Queue::assertPushed(SendClientNotificationJob::class, 3);

    Carbon::setTestNow();
});

test('scenario 2: same project assigned to two clients dispatches two jobs', function () {
    Queue::fake();

    $tz = Timezone::create([
        'name' => 'UTC',
        'label' => 'UTC (UTC+00:00)',
        'offset' => '+00:00',
        'offset_minutes' => 0,
    ]);

    $clientA = Client::factory()->create(['timezone_id' => $tz->id]);
    $clientB = Client::factory()->create(['timezone_id' => $tz->id]);
    $sharedProject = Project::factory()->create(['status' => 'active']);

    $clientA->projects()->attach($sharedProject->id);
    $clientB->projects()->attach($sharedProject->id);

    Carbon::setTestNow(Carbon::parse('09:00', 'UTC'));
    config(['notifications.slots' => ['09:00']]);

    $this->artisan('nudge:send-notifications')
        ->expectsOutput('Dispatched 2 notification job(s).')
        ->assertExitCode(0);

    Queue::assertPushed(SendClientNotificationJob::class, 2);

    Carbon::setTestNow();
});

test('only dispatches jobs for active projects, not on_hold or completed', function () {
    Queue::fake();

    $tz = Timezone::create([
        'name' => 'UTC',
        'label' => 'UTC (UTC+00:00)',
        'offset' => '+00:00',
        'offset_minutes' => 0,
    ]);

    $client = Client::factory()->create(['timezone_id' => $tz->id]);
    $active = Project::factory()->create(['status' => 'active']);
    $onHold = Project::factory()->create(['status' => 'on_hold']);
    $completed = Project::factory()->create(['status' => 'completed']);
    $client->projects()->attach([$active->id, $onHold->id, $completed->id]);

    Carbon::setTestNow(Carbon::parse('09:00', 'UTC'));
    config(['notifications.slots' => ['09:00']]);

    $this->artisan('nudge:send-notifications')
        ->expectsOutput('Dispatched 1 notification job(s).')
        ->assertExitCode(0);

    Queue::assertPushed(SendClientNotificationJob::class, 1);

    Carbon::setTestNow();
});
