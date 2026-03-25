<?php

namespace App\Http\Controllers\Webhook;

use App\Contracts\Repositories\ProjectRepositoryInterface;
use App\Contracts\Repositories\WebhookEventRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Services\ActiveCollabService;
use App\Services\BitlyService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ActiveCollabWebhookController extends Controller
{
    public function __construct(
        private readonly ActiveCollabService $activeCollabService,
        private readonly BitlyService $bitlyService,
        private readonly WebhookEventRepositoryInterface $webhookEventRepository,
        private readonly ProjectRepositoryInterface $projectRepository,
    ) {}

    public function __invoke(Request $request, ?string $webhookToken = null): Response
    {
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

        $secret = $this->activeCollabService->getWebhookSecret();
        $signature = $request->header('X-Angie-WebhookSecret', '');

        if (! $this->activeCollabService->verifySignature($request->getContent(), $signature, $secret)) {
            Log::warning('ActiveCollab webhook signature mismatch', ['ip' => $request->ip()]);

            return response('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        $payload = $request->json()->all();

        $parsed = $this->activeCollabService->parseWebhookPayload($payload);
        $deepLink = $this->activeCollabService->buildDeepLink($payload);
        $shortUrl = $deepLink ? $this->bitlyService->shorten($deepLink) : null;

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
