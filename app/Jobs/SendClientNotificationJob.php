<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\NotificationLog;
use App\Models\WebhookEvent;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

class SendClientNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly Client $client) {}

    public function handle(NotificationService $notificationService): void
    {
        if ($this->alreadySentRecently()) {
            return;
        }

        $since = $this->lastSuccessfulNotificationAt();
        $message = $this->buildMessage($since);

        if (empty($message)) {
            return;
        }

        $notificationService->send($this->client, $message, $since);
    }

    private function alreadySentRecently(): bool
    {
        return NotificationLog::query()
            ->where('client_id', $this->client->id)
            ->where('status', 'sent')
            ->where('sent_at', '>=', now()->subMinutes(60))
            ->exists();
    }

    private function buildMessage(CarbonImmutable $since): string
    {
        $recentEvents = WebhookEvent::query()
            ->with('project')
            ->whereIn('project_id', function ($query): void {
                $query->select('id')
                    ->from('projects')
                    ->where('client_id', $this->client->id)
                    ->where('status', 'active');
            })
            ->where('received_at', '>=', $since)
            ->latest('received_at')
            ->limit(10)
            ->get();

        if ($recentEvents->isEmpty()) {
            return '';
        }

        $sections = $recentEvents
            ->groupBy('project_id')
            ->map(function (Collection $events): string {
                $projectName = $events->first()->project?->name ?? 'Unknown Project';

                $lines = $events->map(function (WebhookEvent $event): string {
                    $title = data_get($event->parsed_data, 'title', 'Project update');
                    $description = $this->formatEventType($event->event_type);
                    $line = "• {$title}: {$description}";

                    if ($event->short_url && config('notifications.include_short_urls', false)) {
                        $line .= "\n  {$event->short_url}";
                    }

                    return $line;
                });

                return "Project: {$projectName}\n".$lines->implode("\n");
            });

        return $sections->implode("\n\n");
    }

    private function formatEventType(string $eventType): string
    {
        $normalized = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $eventType));

        return match ($normalized) {
            'task_created' => 'New task',
            'task_updated' => 'Updated',
            'task_completed' => 'Completed',
            'comment_created' => 'New comment',
            'project_updated' => 'Updated',
            default => ucwords(str_replace('_', ' ', $normalized)),
        };
    }

    private function lastSuccessfulNotificationAt(): CarbonImmutable
    {
        $lastLog = NotificationLog::query()
            ->where('client_id', $this->client->id)
            ->where('status', 'sent')
            ->latest('sent_at')
            ->first();

        if ($lastLog) {
            return CarbonImmutable::parse($lastLog->sent_at);
        }

        $maxLookbackDays = (int) config('notifications.max_lookback_days', 7);

        return CarbonImmutable::now()->subDays($maxLookbackDays);
    }
}
