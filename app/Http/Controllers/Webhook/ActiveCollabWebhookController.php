<?php

namespace App\Http\Controllers\Webhook;

use App\Contracts\Repositories\ProjectRepositoryInterface;
use App\Contracts\Repositories\WebhookEventRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Services\ActiveCollabService;
use App\Services\TinyUrlService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ActiveCollabWebhookController extends Controller
{
    public function __construct(
        private readonly TinyUrlService $tinyUrlService,
        private readonly WebhookEventRepositoryInterface $webhookEventRepository,
        private readonly ProjectRepositoryInterface $projectRepository,
    ) {}

    public function __invoke(Request $request, ?string $webhookToken = null): Response
    {
        Log::channel('webhook')->info('Incoming webhook request', [
            'timestamp' => now()->toIso8601String(),
            'ip' => $request->ip(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'query' => $request->query(),
            'body_raw' => $request->getContent(),
            'body_json' => $request->json()->all(),
            'webhook_token' => $webhookToken,
        ]);

        $tenantId = null;

        if ($webhookToken) {
            $integration = Integration::query()
                ->whereJsonContains('meta->webhook_token', $webhookToken)
                ->where('service', 'activecollab')
                ->first();

            if (! $integration) {
                return response('Not Found', Response::HTTP_NOT_FOUND);
            }

            $tenantId = $integration->tenant_id;
            tenancy()->initialize($integration->tenant);
        }

        /** @var ActiveCollabService $activeCollabService */
        $activeCollabService = app(ActiveCollabService::class);

        $secret = $activeCollabService->getWebhookSecret();
        $signature = $request->header('X-Angie-WebhookSecret', '');
        if (! empty($secret) && ! $activeCollabService->verifySignature($request->getContent(), $signature, $secret)) {
            Log::warning('ActiveCollab webhook signature mismatch', ['ip' => $request->ip()]);

            return response('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        $payload = $request->json()->all();

        $parsed = $activeCollabService->parseWebhookPayload($payload);

        if (
            $parsed['task_name'] === null &&
            $parsed['parent_type'] === 'Task' &&
            $parsed['parent_id'] &&
            $parsed['project_id']
        ) {
            $parsed['task_name'] = $activeCollabService->fetchTaskName(
                (int) $parsed['project_id'],
                (int) $parsed['parent_id']
            );
        }

        $deepLink = $activeCollabService->buildDeepLink($payload);
        $shortUrl = $deepLink ? $this->tinyUrlService->shorten($deepLink) : null;

        $project = null;
        if ($parsed['project_id']) {
            $project = $this->projectRepository->findByExternalId('activecollab', (string) $parsed['project_id']);
        }

        $resolvedTenantId = $tenantId ?? $project?->tenant_id;

        if (is_null($resolvedTenantId)) {
            Log::warning('ActiveCollab webhook received but tenant could not be resolved', [
                'ip' => $request->ip(),
                'project_id' => $parsed['project_id'],
            ]);

            return response('Unprocessable', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->webhookEventRepository->store([
            'tenant_id' => $resolvedTenantId,
            'project_id' => $project?->id,
            'event_type' => $parsed['event_type'],
            'raw_payload' => $payload,
            'parsed_data' => $parsed,
            'activecollab_url' => $deepLink,
            'short_url' => $shortUrl,
            'received_at' => now(),
        ]);

        return response()->noContent();
    }
}
