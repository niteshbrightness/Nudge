<?php

namespace App\Console\Commands;

use App\Jobs\SendClientNotificationJob;
use App\Models\Client;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nudge:send-notifications')]
#[Description('Send scheduled project update notifications to clients based on their timezone and configured time slots.')]
class SendScheduledNotifications extends Command
{
    public function handle(): int
    {
        /** @var array<string> $slots */
        $slots = config('notifications.slots', []);

        if (empty($slots)) {
            $this->warn('No notification slots configured. Set NOTIFICATION_SLOTS in .env');

            return self::SUCCESS;
        }

        $now = CarbonImmutable::now('UTC');

        $clients = Client::query()->with(['timezone'])->get();

        $dispatched = 0;

        foreach ($clients as $client) {
            if (! $client->timezone) {
                continue;
            }

            $localTime = $now->setTimezone($client->timezone->name);

            $shouldNotify = collect($slots)->contains(function (string $slot) use ($localTime): bool {
                [$slotHour, $slotMinute] = array_map('intval', explode(':', $slot));

                $slotMinutes = $slotHour * 60 + $slotMinute;
                $currentMinutes = (int) $localTime->format('G') * 60 + (int) $localTime->format('i');

                return abs($currentMinutes - $slotMinutes) < 15;
            });

            if (! $shouldNotify) {
                continue;
            }

            dispatch(new SendClientNotificationJob($client));
            $dispatched++;
        }

        $this->info("Dispatched notifications for {$dispatched} client(s).");

        return self::SUCCESS;
    }
}
