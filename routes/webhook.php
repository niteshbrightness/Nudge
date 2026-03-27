<?php

use App\Http\Controllers\Webhook\ActiveCollabWebhookController;
use Illuminate\Support\Facades\Route;

// Legacy route (no tenant token) — kept for backward compatibility
Route::any('/webhook/activecollab', ActiveCollabWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('webhook.activecollab');

// Per-tenant token-based route
Route::any('/webhook/activecollab/{webhookToken}', ActiveCollabWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('webhook.activecollab.token');
