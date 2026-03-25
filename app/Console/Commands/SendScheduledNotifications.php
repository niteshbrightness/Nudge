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

        $clients = Client::query()->with(['timezone'])->where('is_active', true)->get();

        $dispatched = 0;

        foreach ($clients as $client) {
            if (! $client->timezone) {
                continue;
            }

            $localTime = $now->setTimezone($client->timezone->name);

            $shouldNotify = collect($slots)->contains(function (string $slot) use ($localTime): bool {
                $slotTime = $localTime->setTimeFromTimeString($slot);
                $diffMinutes = abs($localTime->diffInMinutes($slotTime));

                // Handle midnight wraparound: 23:58 vs 00:02 is 4 minutes apart, not 1436.
                $diffMinutes = min($diffMinutes, 1440 - $diffMinutes);

                return $localTime->gte($slotTime) && $diffMinutes <= 5;
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
