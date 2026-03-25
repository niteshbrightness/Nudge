<?php

use App\Jobs\SendClientNotificationJob;
use App\Models\Client;
use App\Models\NotificationLog;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Timezone;
use App\Models\WebhookEvent;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

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
});

afterEach(function () {
    tenancy()->end();
});

test('sends notification with events since last successful log', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);

    $lastSentAt = now()->subHours(3);

    NotificationLog::create([
        'tenant_id' => 'test-tenant',
        'client_id' => $client->id,
        'channel' => 'twilio',
        'message' => 'Old summary',
        'status' => 'sent',
        'sent_at' => $lastSentAt,
    ]);

    // Event before last sent — should NOT appear
    WebhookEvent::factory()->create([
        'project_id' => $project->id,
        'received_at' => $lastSentAt->subMinute(),
    ]);

    // Event after last sent — SHOULD appear
    WebhookEvent::factory()->create([
        'project_id' => $project->id,
        'parsed_data' => ['title' => 'New task created'],
        'event_type' => 'task_created',
        'received_at' => now()->subHour(),
    ]);

    $this->mock(NotificationService::class, function (MockInterface $mock) {
        $mock->shouldReceive('send')
            ->once()
            ->withArgs(fn ($c, string $message) => str_contains($message, 'New task created'));
    });

    (new SendClientNotificationJob($client))->handle(app(NotificationService::class));
});

test('uses max_lookback_days fallback when no prior log exists', function () {
    config(['notifications.max_lookback_days' => 3]);

    $client = Client::factory()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);

    // Event within 3-day lookback — SHOULD appear
    WebhookEvent::factory()->create([
        'project_id' => $project->id,
        'parsed_data' => ['title' => 'Within lookback'],
        'received_at' => now()->subDays(2),
    ]);

    // Event outside 3-day lookback — should NOT appear
    WebhookEvent::factory()->create([
        'project_id' => $project->id,
        'parsed_data' => ['title' => 'Outside lookback'],
        'received_at' => now()->subDays(4),
    ]);

    $this->mock(NotificationService::class, function (MockInterface $mock) {
        $mock->shouldReceive('send')
            ->once()
            ->withArgs(fn ($c, string $message) => str_contains($message, 'Within lookback') && ! str_contains($message, 'Outside lookback'));
    });

    (new SendClientNotificationJob($client))->handle(app(NotificationService::class));
});

test('skips send when no new events exist since last notification', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);

    NotificationLog::create([
        'tenant_id' => 'test-tenant',
        'client_id' => $client->id,
        'channel' => 'twilio',
        'message' => 'Old summary',
        'status' => 'sent',
        'sent_at' => now()->subHour(),
    ]);

    // Only old event before last notification
    WebhookEvent::factory()->create([
        'project_id' => $project->id,
        'received_at' => now()->subHours(2),
    ]);

    $this->mock(NotificationService::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('send');
    });

    (new SendClientNotificationJob($client))->handle(app(NotificationService::class));
});

test('deduplication guard prevents sending when already sent within 60 minutes', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);

    NotificationLog::create([
        'tenant_id' => 'test-tenant',
        'client_id' => $client->id,
        'channel' => 'twilio',
        'message' => 'Recent summary',
        'status' => 'sent',
        'sent_at' => now()->subMinutes(30),
    ]);

    WebhookEvent::factory()->create([
        'project_id' => $project->id,
        'received_at' => now()->subMinutes(10),
    ]);

    $this->mock(NotificationService::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('send');
    });

    (new SendClientNotificationJob($client))->handle(app(NotificationService::class));
});

test('ignores failed logs when determining last sent time', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);

    $lastSuccessAt = now()->subHours(5);

    // Successful log 5h ago
    NotificationLog::create([
        'tenant_id' => 'test-tenant',
        'client_id' => $client->id,
        'channel' => 'twilio',
        'message' => 'Success',
        'status' => 'sent',
        'sent_at' => $lastSuccessAt,
    ]);

    // Failed log 2h ago — should NOT shrink the lookback window
    NotificationLog::create([
        'tenant_id' => 'test-tenant',
        'client_id' => $client->id,
        'channel' => 'twilio',
        'message' => 'Failed attempt',
        'status' => 'failed',
        'error_message' => 'Twilio error',
        'sent_at' => now()->subHours(2),
    ]);

    // Event between failed log and now — SHOULD appear (lookback from last SUCCESS)
    WebhookEvent::factory()->create([
        'project_id' => $project->id,
        'parsed_data' => ['title' => 'Event after failure'],
        'received_at' => now()->subHour(),
    ]);

    $this->mock(NotificationService::class, function (MockInterface $mock) {
        $mock->shouldReceive('send')
            ->once()
            ->withArgs(fn ($c, string $message) => str_contains($message, 'Event after failure'));
    });

    (new SendClientNotificationJob($client))->handle(app(NotificationService::class));
});

test('includes up to 10 most recent events', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);

    WebhookEvent::factory()->count(12)->create([
        'project_id' => $project->id,
        'received_at' => now()->subHour(),
    ]);

    $capturedMessage = '';

    $this->mock(NotificationService::class, function (MockInterface $mock) use (&$capturedMessage) {
        $mock->shouldReceive('send')
            ->once()
            ->withArgs(function ($c, string $message) use (&$capturedMessage) {
                $capturedMessage = $message;

                return true;
            });
    });

    (new SendClientNotificationJob($client))->handle(app(NotificationService::class));

    expect(substr_count($capturedMessage, '•'))->toBe(10);
});

test('message is grouped by project with project name headers', function () {
    $client = Client::factory()->create();
    $projectA = Project::factory()->create(['client_id' => $client->id, 'name' => 'Alpha Redesign']);
    $projectB = Project::factory()->create(['client_id' => $client->id, 'name' => 'Beta App']);

    WebhookEvent::factory()->create([
        'project_id' => $projectA->id,
        'parsed_data' => ['title' => 'Homepage layout'],
        'event_type' => 'comment_created',
        'short_url' => 'bit.ly/abc123',
        'received_at' => now()->subHour(),
    ]);

    WebhookEvent::factory()->create([
        'project_id' => $projectB->id,
        'parsed_data' => ['title' => 'Login page'],
        'event_type' => 'task_created',
        'short_url' => 'bit.ly/def456',
        'received_at' => now()->subHour(),
    ]);

    $capturedMessage = '';

    $this->mock(NotificationService::class, function (MockInterface $mock) use (&$capturedMessage) {
        $mock->shouldReceive('send')
            ->once()
            ->withArgs(function ($c, string $message) use (&$capturedMessage) {
                $capturedMessage = $message;

                return true;
            });
    });

    (new SendClientNotificationJob($client))->handle(app(NotificationService::class));

    expect($capturedMessage)
        ->toContain('Project: Alpha Redesign')
        ->toContain('• Homepage layout: New comment')
        ->toContain('Project: Beta App')
        ->toContain('• Login page: New task');
});

test('url appears on indented line below event bullet when include_short_urls is enabled', function () {
    config(['notifications.include_short_urls' => true]);

    $client = Client::factory()->create();
    $project = Project::factory()->create(['client_id' => $client->id, 'name' => 'My Project']);

    WebhookEvent::factory()->create([
        'project_id' => $project->id,
        'parsed_data' => ['title' => 'Some task'],
        'event_type' => 'task_updated',
        'short_url' => 'bit.ly/xyz789',
        'received_at' => now()->subHour(),
    ]);

    $capturedMessage = '';

    $this->mock(NotificationService::class, function (MockInterface $mock) use (&$capturedMessage) {
        $mock->shouldReceive('send')
            ->once()
            ->withArgs(function ($c, string $message) use (&$capturedMessage) {
                $capturedMessage = $message;

                return true;
            });
    });

    (new SendClientNotificationJob($client))->handle(app(NotificationService::class));

    expect($capturedMessage)->toContain("• Some task: Updated\n  bit.ly/xyz789");
});
