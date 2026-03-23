<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

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

        $response = Http::withHeaders(['X-Angie-AuthApiToken' => $this->token])
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
        return [
            'event_type' => $payload['event_type'] ?? $payload['type'] ?? 'unknown',
            'project_id' => $payload['payload']['project_id'] ?? $payload['project_id'] ?? null,
            'object_id' => $payload['payload']['id'] ?? null,
            'object_type' => $payload['payload']['class'] ?? null,
            'title' => $payload['payload']['name'] ?? $payload['payload']['body'] ?? null,
            'assignee_id' => $payload['payload']['assignee_id'] ?? null,
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

        $projectId = $payload['payload']['project_id'] ?? $payload['project_id'] ?? null;
        $objectId = $payload['payload']['id'] ?? null;
        $objectType = strtolower($payload['payload']['class'] ?? '');

        if (! $projectId) {
            return null;
        }

        if ($objectId && str_contains($objectType, 'task')) {
            return "{$this->baseUrl}/projects/{$projectId}/tasks/{$objectId}";
        }

        return "{$this->baseUrl}/projects/{$projectId}";
    }

    public function getWebhookSecret(): string
    {
        return $this->webhookSecret;
    }

    /**
     * Verify the webhook signature from ActiveCollab.
     */
    public function verifySignature(string $payload, string $signature, string $secret): bool
    {
        if (empty($secret)) {
            return true;
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }
}
