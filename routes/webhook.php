<?php

use App\Http\Controllers\Webhook\ActiveCollabWebhookController;
use App\Http\Controllers\Webhook\TwilioInboundWebhookController;
use App\Http\Controllers\Webhook\TwilioStatusWebhookController;
use Illuminate\Support\Facades\Route;

// Legacy route (no tenant token) — kept for backward compatibility
Route::any('/webhook/activecollab', ActiveCollabWebhookController::class)
    // ->middleware('throttle:60,1')
    ->name('webhook.activecollab');

// Per-tenant token-based route
Route::any('/webhook/activecollab/{webhookToken}', ActiveCollabWebhookController::class)
    // ->middleware('throttle:60,1')
    ->name('webhook.activecollab.token');

// Twilio inbound SMS (STOP/START opt-out handling)
Route::post('/webhook/twilio/inbound', TwilioInboundWebhookController::class)
    // ->middleware('throttle:60,1')
    ->name('webhook.twilio.inbound');

// Twilio delivery status callbacks
Route::post('/webhook/twilio/status', TwilioStatusWebhookController::class)
    // ->middleware('throttle:120,1')
    ->name('webhook.twilio.status');
