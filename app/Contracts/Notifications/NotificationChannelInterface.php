<?php

namespace App\Contracts\Notifications;

use App\Models\Client;

interface NotificationChannelInterface
{
    public function send(Client $client, string $message): void;
}
