<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\NotificationLog;
use App\Models\Project;
use App\Models\WebhookEvent;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class SendClientNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly Client $client,
        public readonly Project $project,
    ) {}

    public function handle(NotificationService $notificationService): void
    {
        if (! $this->client->sms_consent) {
            return;
        }

        if ($this->alreadySentRecently()) {
            return;
        }

        $since = $this->lastSuccessfulNotificationAt();
        $message = $this->buildMessage($since);

        if (empty($message)) {
            return;
        }

        $notificationService->send($this->client, $message, $since, $this->project->id);
    }

    private function alreadySentRecently(): bool
    {
        return NotificationLog::query()
            ->where('client_id', $this->client->id)
            ->where('project_id', $this->project->id)
            ->where('status', 'sent')
            ->where('sent_at', '>=', now()->subMinutes(15))
            ->exists();
    }

    private function buildMessage(CarbonImmutable $since): string
    {
        $events = WebhookEvent::query()
            ->where('project_id', $this->project->id)
            ->where('received_at', '>=', $since)
            ->latest('received_at')
            ->get();

        if ($events->isEmpty()) {
            return '';
        }

        $lines = $events->map(function (WebhookEvent $event): string {
            $title = data_get($event->parsed_data, 'task_name')
                ?? data_get($event->parsed_data, 'title', 'Project update');
            $description = $this->formatEventType($event->event_type);
            $actorName = data_get($event->parsed_data, 'created_by_name');

            if ($actorName) {
                $description .= " by {$actorName}";
            }

            $line = "• {$title}: {$description}";

            if ($event->short_url) {
                $line .= "\n  {$event->short_url}";
            }

            return $line;
        })->values()->all();

        $header = "Project: {$this->project->name}";
        $maxLength = 1600;
        $includedCount = count($lines);

        while ($includedCount > 0) {
            $body = implode("\n", array_slice($lines, 0, $includedCount));
            $message = $header."\n".$body;
            $truncated = $includedCount < count($lines);

            if ($truncated) {
                $truncatedCount = count($lines) - $includedCount;
                $message .= "\nand {$truncatedCount} more update".($truncatedCount === 1 ? '' : 's');
            }

            if (strlen($message) <= $maxLength) {
                return $message;
            }

            $includedCount--;
        }

        return '';
    }

    private function formatEventType(string $eventType): string
    {
        return match ($eventType) {
            'TaskCreated' => 'New task',
            'TaskUpdated' => 'Status changed',
            'TaskCompleted' => 'Completed',
            'TaskMoved' => 'Moved',
            'TaskDuplicated' => 'Duplicated',
            'CommentCreated' => 'New comment',
            'ProjectCreated' => 'New project',
            'ProjectUpdated' => 'Updated',
            'DiscussionCreated' => 'New discussion',
            'NoteCreated' => 'New note',
            'TimeRecordCreated' => 'Time logged',
            'ExpenseCreated' => 'Expense logged',
            'CompanyCreated' => 'New company',
            'UserInvited' => 'User invited',
            'UserAccepted' => 'User joined',
            'ObjectMovedToTrash' => 'Moved to trash',
            'ObjectRestoredFromTrash' => 'Restored from trash',
            default => Str::headline($eventType),
        };
    }

    private function lastSuccessfulNotificationAt(): CarbonImmutable
    {
        $lastLog = NotificationLog::query()
            ->where('client_id', $this->client->id)
            ->where('project_id', $this->project->id)
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
