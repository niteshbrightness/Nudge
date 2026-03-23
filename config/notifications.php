<?php

use App\Services\Notifications\TwilioChannel;

return [

    /*
    |--------------------------------------------------------------------------
    | Active Notification Channel
    |--------------------------------------------------------------------------
    | The channel driver to use for sending client notifications.
    | Add new channels to the 'channels' map and switch via NOTIFICATION_CHANNEL.
    */
    'channel' => env('NOTIFICATION_CHANNEL', 'twilio'),

    'channels' => [
        'twilio' => TwilioChannel::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Time Slots
    |--------------------------------------------------------------------------
    | Comma-separated HH:MM times (24-hour, evaluated in each client's timezone).
    | The scheduler checks every 15 minutes and fires notifications for clients
    | whose local time falls within a 15-minute window of any defined slot.
    */
    'slots' => array_filter(
        array_map('trim', explode(',', env('NOTIFICATION_SLOTS', '09:00,17:00')))
    ),

    /*
    |--------------------------------------------------------------------------
    | Twilio
    |--------------------------------------------------------------------------
    */
    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_FROM'),
    ],

];
