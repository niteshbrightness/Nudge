<?php

namespace App\Providers;

use App\Contracts\Repositories\ClientRepositoryInterface;
use App\Contracts\Repositories\IntegrationRepositoryInterface;
use App\Contracts\Repositories\NotificationLogRepositoryInterface;
use App\Contracts\Repositories\ProjectRepositoryInterface;
use App\Contracts\Repositories\WebhookEventRepositoryInterface;
use App\Integrations\ActiveCollabIntegration;
use App\ProjectSync\Sources\ActiveCollabSource;
use App\Repositories\ClientRepository;
use App\Repositories\IntegrationRepository;
use App\Repositories\NotificationLogRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\WebhookEventRepository;
use App\Services\ActiveCollabService;
use App\Services\IntegrationManager;
use App\Services\Notifications\TwilioChannel;
use App\Services\NotificationService;
use App\Services\ProjectSyncManager;
use App\Services\TinyUrlService;
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
        $this->app->bind(NotificationLogRepositoryInterface::class, NotificationLogRepository::class);

        $this->app->singleton(IntegrationManager::class, function (): IntegrationManager {
            $manager = new IntegrationManager;
            $manager->register(ActiveCollabIntegration::class);

            return $manager;
        });

        $this->app->singleton(TinyUrlService::class, fn () => new TinyUrlService(
            apiToken: config('services.tinyurl.api_token', ''),
            apiUrl: config('services.tinyurl.api_url', 'https://api.tinyurl.com'),
        ));

        $this->app->singleton(ProjectSyncManager::class, function (): ProjectSyncManager {
            $manager = new ProjectSyncManager;
            $manager->register($this->app->make(ActiveCollabSource::class));

            return $manager;
        });

        $this->app->bind(ActiveCollabService::class, function (): ActiveCollabService {
            /** @var IntegrationManager $manager */
            $manager = $this->app->make(IntegrationManager::class);
            $credentials = $manager->credentials('activecollab') ?? [];

            return new ActiveCollabService(
                baseUrl: rtrim($credentials['url'] ?? config('services.activecollab.url', ''), '/'),
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
