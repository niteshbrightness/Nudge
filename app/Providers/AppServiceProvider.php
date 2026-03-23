<?php

namespace App\Providers;

use App\Contracts\Repositories\ClientRepositoryInterface;
use App\Contracts\Repositories\IntegrationRepositoryInterface;
use App\Contracts\Repositories\ProjectRepositoryInterface;
use App\Contracts\Repositories\WebhookEventRepositoryInterface;
use App\Integrations\ActiveCollabIntegration;
use App\Repositories\ClientRepository;
use App\Repositories\IntegrationRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\WebhookEventRepository;
use App\Services\ActiveCollabService;
use App\Services\BitlyService;
use App\Services\IntegrationManager;
use App\Services\Notifications\TwilioChannel;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ClientRepositoryInterface::class, ClientRepository::class);
        $this->app->bind(ProjectRepositoryInterface::class, ProjectRepository::class);
        $this->app->bind(WebhookEventRepositoryInterface::class, WebhookEventRepository::class);
        $this->app->bind(IntegrationRepositoryInterface::class, IntegrationRepository::class);

        $this->app->singleton(IntegrationManager::class, function (): IntegrationManager {
            $manager = new IntegrationManager;
            $manager->register(ActiveCollabIntegration::class);

            return $manager;
        });

        $this->app->singleton(BitlyService::class, fn () => new BitlyService(
            accessToken: config('services.bitly.access_token', ''),
            apiUrl: config('services.bitly.api_url', 'https://api-ssl.bitly.com/v4'),
        ));

        $this->app->bind(ActiveCollabService::class, function (): ActiveCollabService {
            /** @var IntegrationManager $manager */
            $manager = $this->app->make(IntegrationManager::class);
            $credentials = $manager->credentials('activecollab') ?? [];

            return new ActiveCollabService(
                baseUrl: $credentials['url'] ?? config('services.activecollab.url', ''),
                token: $credentials['token'] ?? config('services.activecollab.token', ''),
                webhookSecret: $credentials['webhook_secret'] ?? config('services.activecollab.webhook_secret', ''),
            );
        });

        $this->app->singleton(TwilioChannel::class, fn () => new TwilioChannel(
            sid: config('notifications.twilio.sid', ''),
            authToken: config('notifications.twilio.auth_token', ''),
            from: config('notifications.twilio.from', ''),
        ));

        $this->app->singleton(NotificationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
