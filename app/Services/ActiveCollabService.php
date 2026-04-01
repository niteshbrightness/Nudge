<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ActiveCollabService
{
    public function __construct(
        private readonly string $baseUrl = '',
        private readonly string $token = '',
        private readonly string $webhookSecret = '',
    ) {}

    /**
     * Fetch all projects from the ActiveCollab API.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchProjects(): array
    {
        if (empty($this->baseUrl) || empty($this->token)) {
            throw new RuntimeException('ActiveCollab is not connected. Please configure it in Integrations.');
        }
        Log::debug('ActiveCollab: fetching projects', ['url' => $this->baseUrl]);
        $response = Http::withToken($this->token)
            ->get("{$this->baseUrl}/api/v1/projects")
            ->throw();

        return $response->json() ?? [];
    }

    /**
     * Parse an incoming webhook payload into a normalized structure.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function parseWebhookPayload(array $payload): array
    {
        $data = $payload['payload'] ?? [];
        $class = $data['class'] ?? null;

        $title = match (true) {
            in_array($class, ['Comment', 'Discussion']) => $data['body_plain_text'] ?? $data['body'] ?? null,
            default => $data['name'] ?? $data['body_plain_text'] ?? $data['body'] ?? null,
        };

        $projectId = $data['project_id'] ?? $payload['project_id'] ?? null;

        if ($projectId === null && isset($data['parent_path'])) {
            preg_match('/projects\/(\d+)/', $data['parent_path'], $matches);
            $projectId = isset($matches[1]) ? (int) $matches[1] : null;
        }

        return [
            'event_type' => $payload['type'] ?? $payload['event'] ?? $payload['event_type'] ?? 'unknown',
            'project_id' => $projectId,
            'object_id' => $data['id'] ?? null,
            'object_type' => $class,
            'title' => $title,
            'task_name' => $class === 'Task' ? ($data['name'] ?? null) : null,
            'assignee_id' => $data['assignee_id'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'parent_type' => $data['parent_type'] ?? null,
            'created_by_name' => $data['created_by_name'] ?? null,
        ];
    }

    /**
     * Build a deep-link URL into ActiveCollab for the given payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function buildDeepLink(array $payload): ?string
    {
        if (empty($this->baseUrl)) {
            return null;
        }

        $data = $payload['payload'] ?? [];
        $urlPath = $data['url_path'] ?? null;
        $projectId = $data['project_id'] ?? $payload['project_id'] ?? null;
        $class = $data['class'] ?? null;
        $parentType = $data['parent_type'] ?? null;
        $parentId = $data['parent_id'] ?? null;

        if ($class === 'Comment' && $parentType === 'Task' && $parentId && $projectId) {
            return "{$this->baseUrl}/projects/{$projectId}/tasks/{$parentId}";
        }

        if ($urlPath) {
            return "{$this->baseUrl}{$urlPath}";
        }

        if ($projectId) {
            return "{$this->baseUrl}/projects/{$projectId}";
        }

        return null;
    }

    /**
     * Fetch a task's name from the ActiveCollab API.
     */
    public function fetchTaskName(int $projectId, int $taskId): ?string
    {
        if (empty($this->baseUrl) || empty($this->token)) {
            return null;
        }

        try {
            $response = Http::withToken($this->token)
                ->get("{$this->baseUrl}/api/v1/projects/{$projectId}/tasks/{$taskId}");

            return $response->json('single.name') ?? $response->json('name') ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function isConfigured(): bool
    {
        return ! empty($this->baseUrl) && ! empty($this->token);
    }

    public function getWebhookSecret(): string
    {
        return $this->webhookSecret;
    }

    /**
     * Verify the webhook signature from ActiveCollab.
     *
     * Returns false when the secret is empty — an unconfigured secret must
     * never be treated as verified, since that would allow arbitrary payloads.
     */
    public function verifySignature(string $payload, string $signature, string $secret): bool
    {
        if (empty($secret)) {
            return false;
        }

        return hash_equals($secret, $signature);
    }
}
