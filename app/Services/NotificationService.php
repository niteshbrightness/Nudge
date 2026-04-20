<?php

namespace App\Services;

use App\Contracts\Notifications\NotificationChannelInterface;
use App\Models\Client;
use App\Models\NotificationLog;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationService
{
    private readonly NotificationChannelInterface $channel;

    public function __construct()
    {
        $this->channel = $this->resolveChannel();
    }

    public function send(Client $client, string $message, ?\DateTimeInterface $queriedSince = null, ?int $projectId = null): void
    {
        $channelName = config('notifications.channel', 'twilio');

        try {
            $twilio_sid = $this->channel->send($client, $message);

            NotificationLog::create([
                'tenant_id' => $client->tenant_id,
                'client_id' => $client->id,
                'project_id' => $projectId,
                'channel' => $channelName,
                'twilio_sid' => $twilio_sid,
                'message' => $message,
                'status' => 'sent',
                'sent_at' => now(),
                'queried_since' => $queriedSince,
            ]);
        } catch (Throwable $e) {
            Log::error('Notification send failed', [
                'client_id' => $client->id,
                'project_id' => $projectId,
                'channel' => $channelName,
                'error' => $e->getMessage(),
            ]);

            NotificationLog::create([
                'tenant_id' => $client->tenant_id,
                'client_id' => $client->id,
                'project_id' => $projectId,
                'channel' => $channelName,
                'message' => $message,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'sent_at' => now(),
                'queried_since' => $queriedSince,
            ]);
        }
    }

    private function resolveChannel(): NotificationChannelInterface
    {
        $channelName = config('notifications.channel', 'twilio');
        $channels = config('notifications.channels', []);

        if (! isset($channels[$channelName])) {
            throw new \InvalidArgumentException("Notification channel [{$channelName}] is not defined in config/notifications.php.");
        }

        return app($channels[$channelName]);
    }
}
