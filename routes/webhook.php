<?php

use App\Http\Controllers\Webhook\ActiveCollabWebhookController;
use Illuminate\Support\Facades\Route;

// Legacy route (no tenant token) — kept for backward compatibility
Route::post('/webhook/activecollab', ActiveCollabWebhookController::class)
    ->name('webhook.activecollab');

// Per-tenant token-based route
Route::post('/webhook/activecollab/{webhookToken}', ActiveCollabWebhookController::class)
    ->name('webhook.activecollab.token');
