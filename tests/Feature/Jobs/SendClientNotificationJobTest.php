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
    $project = Project::factory()->create(['status' => 'active']);
    $client->projects()->attach($project->id);

    $lastSentAt = now()->subHours(3);

    NotificationLog::create([
        'tenant_id' => 'test-tenant',
        'client_id' => $client->id,
        'project_id' => $project->id,
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

    (new SendClientNotificationJob($client, $project))->handle(app(NotificationService::class));
});

test('uses max_lookback_days fallback when no prior log exists', function () {
    config(['notifications.max_lookback_days' => 3]);

    $client = Client::factory()->create();
    $project = Project::factory()->create(['status' => 'active']);
    $client->projects()->attach($project->id);

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

    (new SendClientNotificationJob($client, $project))->handle(app(NotificationService::class));
});

test('skips send when no new events exist since last notification', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create(['status' => 'active']);
    $client->projects()->attach($project->id);

    NotificationLog::create([
        'tenant_id' => 'test-tenant',
        'client_id' => $client->id,
        'project_id' => $project->id,
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

    (new SendClientNotificationJob($client, $project))->handle(app(NotificationService::class));
});

test('deduplication guard prevents sending when already sent within 15 minutes', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create(['status' => 'active']);
    $client->projects()->attach($project->id);

    NotificationLog::create([
        'tenant_id' => 'test-tenant',
        'client_id' => $client->id,
        'project_id' => $project->id,
        'channel' => 'twilio',
        'message' => 'Recent summary',
        'status' => 'sent',
        'sent_at' => now()->subMinutes(10),
    ]);

    WebhookEvent::factory()->create([
        'project_id' => $project->id,
        'received_at' => now()->subMinutes(10),
    ]);

    $this->mock(NotificationService::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('send');
    });

    (new SendClientNotificationJob($client, $project))->handle(app(NotificationService::class));
});

test('deduplication is per-project so different projects are not blocked by each other', function () {
    $client = Client::factory()->create();
    $projectA = Project::factory()->create(['name' => 'Project A', 'status' => 'active']);
    $projectB = Project::factory()->create(['name' => 'Project B', 'status' => 'active']);
    $client->projects()->attach([$projectA->id, $projectB->id]);

    // Project A was sent recently — should block A but NOT B
    NotificationLog::create([
        'tenant_id' => 'test-tenant',
        'client_id' => $client->id,
        'project_id' => $projectA->id,
        'channel' => 'twilio',
        'message' => 'Recent A',
        'status' => 'sent',
        'sent_at' => now()->subMinutes(5),
    ]);

    WebhookEvent::factory()->create([
        'project_id' => $projectB->id,
        'parsed_data' => ['title' => 'Task in B'],
        'received_at' => now()->subMinutes(10),
    ]);

    $this->mock(NotificationService::class, function (MockInterface $mock) {
        $mock->shouldReceive('send')
            ->once()
            ->withArgs(fn ($c, string $message) => str_contains($message, 'Project B'));
    });

    (new SendClientNotificationJob($client, $projectA))->handle(app(NotificationService::class));
    (new SendClientNotificationJob($client, $projectB))->handle(app(NotificationService::class));
});

test('uses sent_at not queried_since as cutoff for next notification', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create(['status' => 'active']);
    $client->projects()->attach($project->id);

    $sentAt = now()->subHours(2);

    // Previous log with queried_since pointing far back — should NOT be used as cutoff
    NotificationLog::create([
        'tenant_id' => 'test-tenant',
        'client_id' => $client->id,
        'project_id' => $project->id,
        'channel' => 'twilio',
        'message' => 'Old summary',
        'status' => 'sent',
        'sent_at' => $sentAt,
        'queried_since' => now()->subDays(7),
    ]);

    // Old event within queried_since window but before sent_at — should NOT appear
    WebhookEvent::factory()->create([
        'project_id' => $project->id,
        'parsed_data' => ['title' => 'Old event'],
        'received_at' => now()->subDays(3),
    ]);

    // New event after sent_at — SHOULD appear
    WebhookEvent::factory()->create([
        'project_id' => $project->id,
        'parsed_data' => ['title' => 'New event after last send'],
        'event_type' => 'task_created',
        'received_at' => now()->subHour(),
    ]);

    $this->mock(NotificationService::class, function (MockInterface $mock) {
        $mock->shouldReceive('send')
            ->once()
            ->withArgs(fn ($c, string $message) => str_contains($message, 'New event after last send') && ! str_contains($message, 'Old event'));
    });

    (new SendClientNotificationJob($client, $project))->handle(app(NotificationService::class));
});

test('ignores failed logs when determining last sent time', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create(['status' => 'active']);
    $client->projects()->attach($project->id);

    $lastSuccessAt = now()->subHours(5);

    // Successful log 5h ago
    NotificationLog::create([
        'tenant_id' => 'test-tenant',
        'client_id' => $client->id,
        'project_id' => $project->id,
        'channel' => 'twilio',
        'message' => 'Success',
        'status' => 'sent',
        'sent_at' => $lastSuccessAt,
    ]);

    // Failed log 2h ago — should NOT shrink the lookback window
    NotificationLog::create([
        'tenant_id' => 'test-tenant',
        'client_id' => $client->id,
        'project_id' => $project->id,
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

    (new SendClientNotificationJob($client, $project))->handle(app(NotificationService::class));
});

test('all events are included when they fit within 1600 chars', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create(['status' => 'active']);
    $client->projects()->attach($project->id);

    WebhookEvent::factory()->count(7)->create([
        'project_id' => $project->id,
        'parsed_data' => ['title' => 'Short title'],
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

    (new SendClientNotificationJob($client, $project))->handle(app(NotificationService::class));

    expect(substr_count($capturedMessage, '•'))->toBe(7);
});

test('message does not exceed 1600 characters when events are long', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create(['name' => 'My Project', 'status' => 'active']);
    $client->projects()->attach($project->id);

    $longTitle = str_repeat('A', 300);

    WebhookEvent::factory()->count(10)->create([
        'project_id' => $project->id,
        'parsed_data' => ['title' => $longTitle],
        'event_type' => 'TaskUpdated',
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

    (new SendClientNotificationJob($client, $project))->handle(app(NotificationService::class));

    expect(strlen($capturedMessage))->toBeLessThanOrEqual(1600);
});

test('suffix appended when events are dropped due to character limit', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create(['name' => 'My Project', 'status' => 'active']);
    $client->projects()->attach($project->id);

    $longTitle = str_repeat('B', 300);

    WebhookEvent::factory()->count(10)->create([
        'project_id' => $project->id,
        'parsed_data' => ['title' => $longTitle],
        'event_type' => 'TaskUpdated',
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

    (new SendClientNotificationJob($client, $project))->handle(app(NotificationService::class));

    expect($capturedMessage)->toMatch('/and \d+ more updates/');
});

test('suffix shows exact count of dropped events', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create(['name' => 'My Project', 'status' => 'active']);
    $client->projects()->attach($project->id);

    // Each event is ~320 chars; 5 events = ~1600 chars header included, so some will be dropped
    $longTitle = str_repeat('C', 300);

    WebhookEvent::factory()->count(10)->sequence(
        fn ($s) => ['received_at' => now()->subMinutes($s->index + 1)]
    )->create([
        'project_id' => $project->id,
        'parsed_data' => ['title' => $longTitle],
        'event_type' => 'TaskUpdated',
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

    (new SendClientNotificationJob($client, $project))->handle(app(NotificationService::class));

    preg_match('/and (\d+) more updates/', $capturedMessage, $matches);
    $droppedCount = (int) $matches[1];
    $includedCount = substr_count($capturedMessage, '•');

    expect($includedCount + $droppedCount)->toBe(10);
});

test('no suffix when all events fit within 1600 chars', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create(['name' => 'My Project', 'status' => 'active']);
    $client->projects()->attach($project->id);

    WebhookEvent::factory()->count(3)->create([
        'project_id' => $project->id,
        'parsed_data' => ['title' => 'Short task'],
        'event_type' => 'TaskUpdated',
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

    (new SendClientNotificationJob($client, $project))->handle(app(NotificationService::class));

    expect($capturedMessage)->not->toContain('more updates');
});

test('message includes project name header', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create(['name' => 'Alpha Redesign', 'status' => 'active']);
    $client->projects()->attach($project->id);

    WebhookEvent::factory()->create([
        'project_id' => $project->id,
        'parsed_data' => ['title' => 'Some long comment text', 'task_name' => 'Homepage layout'],
        'event_type' => 'CommentCreated',
        'short_url' => 'tinyurl.com/abc123',
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

    (new SendClientNotificationJob($client, $project))->handle(app(NotificationService::class));

    expect($capturedMessage)
        ->toContain('Project: Alpha Redesign')
        ->toContain('• Homepage layout: New comment');
});

test('url appears on indented line below event bullet when short_url is set', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create(['name' => 'My Project', 'status' => 'active']);
    $client->projects()->attach($project->id);

    WebhookEvent::factory()->create([
        'project_id' => $project->id,
        'parsed_data' => ['title' => 'Some task'],
        'event_type' => 'TaskUpdated',
        'short_url' => 'tinyurl.com/xyz789',
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

    (new SendClientNotificationJob($client, $project))->handle(app(NotificationService::class));

    expect($capturedMessage)->toContain("• Some task: Status changed\n  tinyurl.com/xyz789");
});

test('task_name is preferred over title when both are present', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create(['status' => 'active']);
    $client->projects()->attach($project->id);

    WebhookEvent::factory()->create([
        'project_id' => $project->id,
        'parsed_data' => ['title' => 'Long comment body text here', 'task_name' => 'My Task'],
        'event_type' => 'CommentCreated',
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

    (new SendClientNotificationJob($client, $project))->handle(app(NotificationService::class));

    expect($capturedMessage)
        ->toContain('My Task')
        ->not->toContain('Long comment body text here');
});

test('falls back to title when task_name is absent', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create(['status' => 'active']);
    $client->projects()->attach($project->id);

    WebhookEvent::factory()->create([
        'project_id' => $project->id,
        'parsed_data' => ['title' => 'Fallback title'],
        'event_type' => 'TaskCreated',
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

    (new SendClientNotificationJob($client, $project))->handle(app(NotificationService::class));

    expect($capturedMessage)->toContain('Fallback title');
});

test('actor name appears in message when created_by_name is set', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create(['name' => 'Alpha Redesign', 'status' => 'active']);
    $client->projects()->attach($project->id);

    WebhookEvent::factory()->create([
        'project_id' => $project->id,
        'parsed_data' => ['title' => 'Homepage layout', 'created_by_name' => 'John'],
        'event_type' => 'CommentCreated',
        'received_at' => now()->subHour(),
    ]);

    WebhookEvent::factory()->create([
        'project_id' => $project->id,
        'parsed_data' => ['title' => 'API integration', 'created_by_name' => 'Sarah'],
        'event_type' => 'TaskUpdated',
        'received_at' => now()->subMinutes(30),
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

    (new SendClientNotificationJob($client, $project))->handle(app(NotificationService::class));

    expect($capturedMessage)
        ->toContain('• Homepage layout: New comment by John')
        ->toContain('• API integration: Status changed by Sarah');
});

// Scenario 1: Client with 2 projects gets 2 separate SMS
test('client with two projects dispatches separate jobs producing separate messages', function () {
    $client = Client::factory()->create();
    $projectA = Project::factory()->create(['name' => 'Project Alpha', 'status' => 'active']);
    $projectB = Project::factory()->create(['name' => 'Project Beta', 'status' => 'active']);
    $client->projects()->attach([$projectA->id, $projectB->id]);

    WebhookEvent::factory()->create([
        'project_id' => $projectA->id,
        'parsed_data' => ['title' => 'Alpha task'],
        'received_at' => now()->subHour(),
    ]);

    WebhookEvent::factory()->create([
        'project_id' => $projectB->id,
        'parsed_data' => ['title' => 'Beta task'],
        'received_at' => now()->subHour(),
    ]);

    $sentMessages = [];

    $this->mock(NotificationService::class, function (MockInterface $mock) use (&$sentMessages) {
        $mock->shouldReceive('send')
            ->twice()
            ->withArgs(function ($c, string $message) use (&$sentMessages) {
                $sentMessages[] = $message;

                return true;
            });
    });

    (new SendClientNotificationJob($client, $projectA))->handle(app(NotificationService::class));
    (new SendClientNotificationJob($client, $projectB))->handle(app(NotificationService::class));

    expect($sentMessages)->toHaveCount(2);
    expect($sentMessages[0])->toContain('Project Alpha')->toContain('Alpha task');
    expect($sentMessages[1])->toContain('Project Beta')->toContain('Beta task');
});

// Scenario 2: Same project assigned to two clients, both receive SMS
test('two clients assigned to same project each receive their own SMS', function () {
    $clientA = Client::factory()->create(['name' => 'Client A']);
    $clientB = Client::factory()->create(['name' => 'Client B']);
    $project = Project::factory()->create(['name' => 'Shared Project', 'status' => 'active']);

    $clientA->projects()->attach($project->id);
    $clientB->projects()->attach($project->id);

    WebhookEvent::factory()->create([
        'project_id' => $project->id,
        'parsed_data' => ['title' => 'Shared update'],
        'received_at' => now()->subHour(),
    ]);

    $notifiedClients = [];

    $this->mock(NotificationService::class, function (MockInterface $mock) use (&$notifiedClients) {
        $mock->shouldReceive('send')
            ->twice()
            ->withArgs(function (Client $c, string $message) use (&$notifiedClients) {
                $notifiedClients[] = $c->name;

                return str_contains($message, 'Shared Project');
            });
    });

    (new SendClientNotificationJob($clientA, $project))->handle(app(NotificationService::class));
    (new SendClientNotificationJob($clientB, $project))->handle(app(NotificationService::class));

    expect($notifiedClients)->toContain('Client A')->toContain('Client B');
});

test('does not send SMS when client has no sms_consent', function () {
    $client = Client::factory()->create(['sms_consent' => false]);
    $project = Project::factory()->create(['status' => 'active']);
    $client->projects()->attach($project->id);

    WebhookEvent::factory()->create([
        'project_id' => $project->id,
        'parsed_data' => ['title' => 'Some update'],
        'received_at' => now()->subHour(),
    ]);

    $this->mock(NotificationService::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('send');
    });

    (new SendClientNotificationJob($client, $project))->handle(app(NotificationService::class));
});
