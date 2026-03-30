<?php

use App\Models\Integration;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use App\Services\ActiveCollabService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────────────────────
// verifySignature
// ──────────────────────────────────────────────────────────────

describe('ActiveCollabService::verifySignature', function () {
    it('passes when signature equals the configured secret', function () {
        $service = new ActiveCollabService(webhookSecret: 'bab22852-3e58-48bd-9a2d-c6d9d6ec1c31');

        expect($service->verifySignature('any-payload', 'bab22852-3e58-48bd-9a2d-c6d9d6ec1c31', 'bab22852-3e58-48bd-9a2d-c6d9d6ec1c31'))->toBeTrue();
    });

    it('fails when signature does not match the secret', function () {
        $service = new ActiveCollabService(webhookSecret: 'correct-secret');

        expect($service->verifySignature('any-payload', 'wrong-secret', 'correct-secret'))->toBeFalse();
    });

    it('fails when secret is empty', function () {
        $service = new ActiveCollabService;

        expect($service->verifySignature('any-payload', 'some-value', ''))->toBeFalse();
    });
});

// ──────────────────────────────────────────────────────────────
// parseWebhookPayload
// ──────────────────────────────────────────────────────────────

describe('ActiveCollabService::parseWebhookPayload', function () {
    it('parses a CommentCreated payload correctly', function () {
        $service = new ActiveCollabService;

        $payload = [
            'instance_id' => 231686,
            'type' => 'CommentCreated',
            'timestamp' => 1774843804,
            'payload' => [
                'class' => 'Comment',
                'id' => 116917,
                'url_path' => '/comments/116917',
                'project_id' => 2096,
                'parent_type' => 'Task',
                'parent_id' => 41996,
                'body_plain_text' => 'Testing',
                'body' => '<p>Testing </p>',
                'created_by_name' => 'Harit Soni',
                'created_by_email' => 'h.soni@brightness-india.com',
            ],
        ];

        $parsed = $service->parseWebhookPayload($payload);

        expect($parsed['event_type'])->toBe('CommentCreated')
            ->and($parsed['project_id'])->toBe(2096)
            ->and($parsed['object_id'])->toBe(116917)
            ->and($parsed['object_type'])->toBe('Comment')
            ->and($parsed['title'])->toBe('Testing')
            ->and($parsed['task_name'])->toBeNull()
            ->and($parsed['parent_id'])->toBe(41996)
            ->and($parsed['parent_type'])->toBe('Task')
            ->and($parsed['created_by_name'])->toBe('Harit Soni');
    });

    it('uses body_plain_text over raw HTML body for comments', function () {
        $service = new ActiveCollabService;

        $payload = [
            'type' => 'CommentCreated',
            'payload' => [
                'class' => 'Comment',
                'id' => 1,
                'project_id' => 10,
                'body_plain_text' => 'Plain text content',
                'body' => '<p>HTML content</p>',
            ],
        ];

        expect($service->parseWebhookPayload($payload)['title'])->toBe('Plain text content');
    });

    it('parses a TaskCreated payload correctly', function () {
        $service = new ActiveCollabService;

        $payload = [
            'type' => 'TaskCreated',
            'payload' => [
                'class' => 'Task',
                'id' => 41996,
                'url_path' => '/tasks/41996',
                'project_id' => 2096,
                'name' => 'Build login page',
                'assignee_id' => 5,
                'created_by_name' => 'John Doe',
            ],
        ];

        $parsed = $service->parseWebhookPayload($payload);

        expect($parsed['event_type'])->toBe('TaskCreated')
            ->and($parsed['project_id'])->toBe(2096)
            ->and($parsed['object_id'])->toBe(41996)
            ->and($parsed['object_type'])->toBe('Task')
            ->and($parsed['title'])->toBe('Build login page')
            ->and($parsed['task_name'])->toBe('Build login page')
            ->and($parsed['assignee_id'])->toBe(5)
            ->and($parsed['parent_id'])->toBeNull()
            ->and($parsed['parent_type'])->toBeNull()
            ->and($parsed['created_by_name'])->toBe('John Doe');
    });

    it('parses a DiscussionCreated payload using body_plain_text as title', function () {
        $service = new ActiveCollabService;

        $payload = [
            'type' => 'DiscussionCreated',
            'payload' => [
                'class' => 'Discussion',
                'id' => 500,
                'project_id' => 2096,
                'body_plain_text' => 'Kick-off discussion',
                'body' => '<p>Kick-off discussion</p>',
            ],
        ];

        $parsed = $service->parseWebhookPayload($payload);

        expect($parsed['event_type'])->toBe('DiscussionCreated')
            ->and($parsed['title'])->toBe('Kick-off discussion')
            ->and($parsed['task_name'])->toBeNull();
    });

    it('extracts project_id from parent_path when project_id is absent', function () {
        $service = new ActiveCollabService;

        $payload = [
            'type' => 'ActivityLogCreated',
            'instance_id' => 231686,
            'timestamp' => 1774849473,
            'payload' => [
                'class' => 'SubtaskCreatedActivityLog',
                'id' => 324803,
                'url_path' => '/activity-logs/324803',
                'parent_type' => 'Task',
                'parent_id' => 41996,
                'parent_path' => 'projects/2096/visible-to-clients/tasks/41996',
                'created_by_name' => 'Harit Soni',
            ],
        ];

        $parsed = $service->parseWebhookPayload($payload);

        expect($parsed['event_type'])->toBe('ActivityLogCreated')
            ->and($parsed['project_id'])->toBe(2096)
            ->and($parsed['object_id'])->toBe(324803)
            ->and($parsed['object_type'])->toBe('SubtaskCreatedActivityLog')
            ->and($parsed['parent_id'])->toBe(41996)
            ->and($parsed['parent_type'])->toBe('Task')
            ->and($parsed['created_by_name'])->toBe('Harit Soni');
    });

    it('returns null project_id when parent_path has no project segment', function () {
        $service = new ActiveCollabService;

        $payload = [
            'type' => 'UnknownEvent',
            'payload' => [
                'class' => 'SomeClass',
                'id' => 1,
                'parent_path' => 'users/42/tasks/99',
            ],
        ];

        expect($service->parseWebhookPayload($payload)['project_id'])->toBeNull();
    });
});

// ──────────────────────────────────────────────────────────────
// buildDeepLink
// ──────────────────────────────────────────────────────────────

describe('ActiveCollabService::buildDeepLink', function () {
    it('builds link from url_path when present', function () {
        $service = new ActiveCollabService(baseUrl: 'https://app.activecollab.com/231686');

        $payload = [
            'payload' => [
                'url_path' => '/comments/116917',
                'project_id' => 2096,
            ],
        ];

        expect($service->buildDeepLink($payload))->toBe('https://app.activecollab.com/231686/comments/116917');
    });

    it('falls back to project URL when url_path is absent', function () {
        $service = new ActiveCollabService(baseUrl: 'https://app.activecollab.com/231686');

        $payload = [
            'payload' => [
                'project_id' => 2096,
            ],
        ];

        expect($service->buildDeepLink($payload))->toBe('https://app.activecollab.com/231686/projects/2096');
    });

    it('returns null when baseUrl is not configured', function () {
        $service = new ActiveCollabService(baseUrl: '');

        $payload = [
            'payload' => [
                'url_path' => '/comments/1',
                'project_id' => 1,
            ],
        ];

        expect($service->buildDeepLink($payload))->toBeNull();
    });

    it('returns null when neither url_path nor project_id is present', function () {
        $service = new ActiveCollabService(baseUrl: 'https://app.activecollab.com/231686');

        expect($service->buildDeepLink(['payload' => []]))->toBeNull();
    });

    it('returns parent task URL for comment on task instead of comment url_path', function () {
        $service = new ActiveCollabService(baseUrl: 'https://app.activecollab.com/231686');

        $payload = [
            'payload' => [
                'class' => 'Comment',
                'id' => 116917,
                'url_path' => '/comments/116917',
                'project_id' => 2096,
                'parent_type' => 'Task',
                'parent_id' => 41996,
            ],
        ];

        expect($service->buildDeepLink($payload))
            ->toBe('https://app.activecollab.com/231686/projects/2096/tasks/41996');
    });
});

// ──────────────────────────────────────────────────────────────
// fetchTaskName
// ──────────────────────────────────────────────────────────────

describe('ActiveCollabService::fetchTaskName', function () {
    it('returns the task name from the API', function () {
        Http::fake([
            '*/api/v1/projects/2096/tasks/41996' => Http::response(['single' => ['name' => 'Homepage layout', 'id' => 41996]]),
        ]);

        $service = new ActiveCollabService(baseUrl: 'https://app.activecollab.com/231686', token: 'test-token');

        expect($service->fetchTaskName(2096, 41996))->toBe('Homepage layout');
    });

    it('returns null when the API response has no name field', function () {
        Http::fake([
            '*/api/v1/projects/*/tasks/*' => Http::response([]),
        ]);

        $service = new ActiveCollabService(baseUrl: 'https://app.activecollab.com/231686', token: 'test-token');

        expect($service->fetchTaskName(2096, 41996))->toBeNull();
    });

    it('returns null when service is not configured', function () {
        $service = new ActiveCollabService;

        expect($service->fetchTaskName(2096, 41996))->toBeNull();
    });

    it('returns null and does not throw when the API call fails', function () {
        Http::fake([
            '*/api/v1/projects/*/tasks/*' => Http::response(null, 500),
        ]);

        $service = new ActiveCollabService(baseUrl: 'https://app.activecollab.com/231686', token: 'test-token');

        expect($service->fetchTaskName(2096, 41996))->toBeNull();
    });
});

// ──────────────────────────────────────────────────────────────
// Webhook endpoint HTTP tests
// ──────────────────────────────────────────────────────────────

describe('Webhook endpoint', function () {
    it('returns 404 when webhook token does not match any integration', function () {
        $this->postJson('/webhook/activecollab/non-existent-token', [])
            ->assertNotFound();
    });

    it('stores parent task name as title for CommentCreated events', function () {
        $tenant = Tenant::create(['id' => 'test-tenant', 'name' => 'Test Tenant']);
        $webhookToken = 'test-webhook-token-uuid';

        Integration::create([
            'tenant_id' => 'test-tenant',
            'service' => 'activecollab',
            'credentials' => [
                'url' => 'https://app.activecollab.com/231686',
                'token' => 'api-token',
            ],
            'meta' => ['webhook_token' => $webhookToken],
            'is_active' => true,
        ]);

        Http::fake([
            '*/api/v1/projects/2096/tasks/41996' => Http::response(['single' => ['name' => 'Homepage layout', 'id' => 41996]]),
        ]);

        $this->postJson(
            "/webhook/activecollab/{$webhookToken}",
            [
                'instance_id' => 231686,
                'type' => 'CommentCreated',
                'timestamp' => now()->timestamp,
                'payload' => [
                    'class' => 'Comment',
                    'id' => 116917,
                    'url_path' => '/comments/116917',
                    'project_id' => 2096,
                    'parent_type' => 'Task',
                    'parent_id' => 41996,
                    'body_plain_text' => 'This is a very long comment that should not appear in the SMS',
                    'body' => '<p>This is a very long comment that should not appear in the SMS</p>',
                    'created_by_name' => 'Steve',
                ],
            ]
        )->assertNoContent();

        tenancy()->initialize($tenant);
        $event = WebhookEvent::first();
        expect($event->parsed_data['task_name'])->toBe('Homepage layout')
            ->and($event->parsed_data['title'])->toBe('This is a very long comment that should not appear in the SMS');
        tenancy()->end();
    });

    it('falls back to comment body when task API call returns no name', function () {
        $tenant = Tenant::create(['id' => 'test-tenant2', 'name' => 'Test Tenant 2']);
        $webhookToken = 'test-webhook-token-fallback';

        Integration::create([
            'tenant_id' => 'test-tenant2',
            'service' => 'activecollab',
            'credentials' => [
                'url' => 'https://app.activecollab.com/231686',
                'token' => 'api-token',
            ],
            'meta' => ['webhook_token' => $webhookToken],
            'is_active' => true,
        ]);

        Http::fake([
            '*/api/v1/projects/*/tasks/*' => Http::response([]),
        ]);

        $this->postJson(
            "/webhook/activecollab/{$webhookToken}",
            [
                'instance_id' => 231686,
                'type' => 'CommentCreated',
                'timestamp' => now()->timestamp,
                'payload' => [
                    'class' => 'Comment',
                    'id' => 999,
                    'project_id' => 2096,
                    'parent_type' => 'Task',
                    'parent_id' => 41996,
                    'body_plain_text' => 'Fallback comment text',
                    'created_by_name' => 'Steve',
                ],
            ]
        )->assertNoContent();

        tenancy()->initialize($tenant);
        $event = WebhookEvent::first();
        expect($event->parsed_data['task_name'])->toBeNull()
            ->and($event->parsed_data['title'])->toBe('Fallback comment text');
        tenancy()->end();
    });

    it('returns 401 when webhook secret header does not match', function () {
        Tenant::create(['id' => 'test-tenant', 'name' => 'Test Tenant']);

        $webhookToken = 'test-webhook-token-uuid';

        Integration::create([
            'tenant_id' => 'test-tenant',
            'service' => 'activecollab',
            'credentials' => [
                'url' => 'https://app.activecollab.com/231686',
                'token' => 'api-token',
                'webhook_secret' => 'correct-secret',
            ],
            'meta' => ['webhook_token' => $webhookToken],
            'is_active' => true,
        ]);

        $this->postJson(
            "/webhook/activecollab/{$webhookToken}",
            [
                'type' => 'CommentCreated',
                'instance_id' => 231686,
                'timestamp' => now()->timestamp,
                'payload' => [
                    'class' => 'Comment',
                    'id' => 1,
                    'project_id' => 1,
                ],
            ],
            ['X-Angie-WebhookSecret' => 'wrong-secret']
        )->assertUnauthorized();

        tenancy()->end();
    });
});
