<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\WebhookEvent;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nudge:send-notifications')]
#[Description('Send scheduled project update notifications to clients based on their timezone and configured time slots.')]
class SendScheduledNotifications extends Command
{
    public function handle(NotificationService $notificationService): int
    {
        /** @var array<string> $slots */
        $slots = config('notifications.slots', []);

        if (empty($slots)) {
            $this->warn('No notification slots configured. Set NOTIFICATION_SLOTS in .env');

            return self::SUCCESS;
        }

        $now = CarbonImmutable::now('UTC');

        // Load all clients with their timezone, grouped by timezone
        $clients = Client::query()->with(['timezone'])->get();

        $notified = 0;

        foreach ($clients as $client) {
            if (! $client->timezone) {
                continue;
            }

            $localTime = $now->setTimezone($client->timezone->name);

            $shouldNotify = collect($slots)->contains(function (string $slot) use ($localTime): bool {
                [$slotHour, $slotMinute] = array_map('intval', explode(':', $slot));

                // Match within a 15-minute window of the slot
                $slotMinutes = $slotHour * 60 + $slotMinute;
                $currentMinutes = (int) $localTime->format('G') * 60 + (int) $localTime->format('i');

                return abs($currentMinutes - $slotMinutes) < 15;
            });

            if (! $shouldNotify) {
                continue;
            }

            $message = $this->buildMessage($client->id);

            if (empty($message)) {
                continue;
            }

            $notificationService->send($client, $message);
            $notified++;
        }

        $this->info("Sent notifications to {$notified} client(s).");

        return self::SUCCESS;
    }

    private function buildMessage(int $clientId): string
    {
        $recentEvents = WebhookEvent::query()
            ->where('project_id', function ($query) use ($clientId) {
                $query->select('id')
                    ->from('projects')
                    ->where('client_id', $clientId);
            })
            ->where('received_at', '>=', now()->subHours(24))
            ->latest('received_at')
            ->limit(5)
            ->get();

        if ($recentEvents->isEmpty()) {
            return '';
        }

        $lines = $recentEvents->map(fn (WebhookEvent $event): string => sprintf(
            '• [%s] %s%s',
            $event->event_type,
            data_get($event->parsed_data, 'title', 'Project update'),
            $event->short_url ? " — {$event->short_url}" : '',
        ));

        return "Project updates in the last 24h:\n".$lines->implode("\n");
    }
}
