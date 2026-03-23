<?php

namespace App\Http\Controllers\Integrations;

use App\Contracts\Repositories\IntegrationRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Integrations\StoreIntegrationRequest;
use App\Models\Integration;
use App\Services\IntegrationManager;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function __construct(
        private readonly IntegrationManager $manager,
        private readonly IntegrationRepositoryInterface $integrations,
    ) {}

    public function index(): Response
    {
        $connected = $this->integrations->allForTenant()->keyBy('service');

        $definitions = collect($this->manager->all())->map(function (string $class) use ($connected): array {
            $integration = $connected->get($class::service());

            return [
                'service' => $class::service(),
                'label' => $class::label(),
                'description' => $class::description(),
                'logoIcon' => $class::logoIcon(),
                'hasWebhook' => $class::hasWebhook(),
                'isConnected' => $integration !== null,
                'integrationId' => $integration?->id,
            ];
        })->values();

        return Inertia::render('integrations/index', [
            'definitions' => $definitions,
        ]);
    }

    public function create(string $service): Response
    {
        $class = $this->manager->get($service);

        return Inertia::render('integrations/setup', [
            'definition' => [
                'service' => $class::service(),
                'label' => $class::label(),
                'description' => $class::description(),
                'logoIcon' => $class::logoIcon(),
                'credentialFields' => $class::credentialFields(),
                'setupSteps' => $class::setupSteps(),
                'hasWebhook' => $class::hasWebhook(),
            ],
            'integration' => null,
            'webhookUrl' => null,
        ]);
    }

    public function store(StoreIntegrationRequest $request, string $service): RedirectResponse
    {
        $this->integrations->upsert($service, $request->validated());

        return redirect()->route('integrations.index')->with('success', ucfirst($service).' connected successfully.');
    }

    public function edit(Integration $integration): Response
    {
        $class = $this->manager->get($integration->service);
        $webhookUrl = null;

        if ($class::hasWebhook()) {
            $token = $integration->meta['webhook_token'] ?? null;
            if ($token) {
                $webhookUrl = url("/webhook/{$integration->service}/{$token}");
            }
        }

        $credentials = $integration->credentials ?? [];
        $maskedCredentials = [];
        foreach ($class::credentialFields() as $field) {
            $name = $field['name'];
            if ($field['type'] === 'password' && ! empty($credentials[$name])) {
                $maskedCredentials[$name] = '••••••••';
            } else {
                $maskedCredentials[$name] = $credentials[$name] ?? '';
            }
        }

        return Inertia::render('integrations/setup', [
            'definition' => [
                'service' => $class::service(),
                'label' => $class::label(),
                'description' => $class::description(),
                'logoIcon' => $class::logoIcon(),
                'credentialFields' => $class::credentialFields(),
                'setupSteps' => $class::setupSteps(),
                'hasWebhook' => $class::hasWebhook(),
            ],
            'integration' => [
                'id' => $integration->id,
                'credentials' => $maskedCredentials,
            ],
            'webhookUrl' => $webhookUrl,
        ]);
    }

    public function update(StoreIntegrationRequest $request, Integration $integration): RedirectResponse
    {
        $existing = $integration->credentials ?? [];
        $incoming = $request->validated();

        $merged = array_merge($existing, array_filter($incoming, fn ($v) => $v !== '••••••••'));

        $this->integrations->upsert($integration->service, $merged);

        return redirect()->route('integrations.index')->with('success', 'Integration updated successfully.');
    }

    public function destroy(Integration $integration): RedirectResponse
    {
        $this->integrations->delete($integration);

        return redirect()->route('integrations.index')->with('success', 'Integration disconnected.');
    }
}
