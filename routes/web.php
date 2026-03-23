<?php

use App\Http\Controllers\Clients\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Integrations\IntegrationController;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\WebhookEvents\WebhookEventController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified', 'tenant'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Clients
    Route::resource('clients', ClientController::class)->except(['show']);

    // Projects
    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('projects/sync', [ProjectController::class, 'sync'])->name('projects.sync');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::put('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');

    // Webhook event log (read-only)
    Route::get('webhooks', [WebhookEventController::class, 'index'])->name('webhooks.index');
    Route::get('webhooks/{webhookEvent}', [WebhookEventController::class, 'show'])->name('webhooks.show');

    // Integrations
    Route::get('integrations', [IntegrationController::class, 'index'])->name('integrations.index');
    Route::get('integrations/{service}/connect', [IntegrationController::class, 'create'])->name('integrations.create');
    Route::post('integrations/{service}', [IntegrationController::class, 'store'])->name('integrations.store');
    Route::get('integrations/{integration}/edit', [IntegrationController::class, 'edit'])->name('integrations.edit');
    Route::put('integrations/{integration}', [IntegrationController::class, 'update'])->name('integrations.update');
    Route::delete('integrations/{integration}', [IntegrationController::class, 'destroy'])->name('integrations.destroy');
});

require __DIR__.'/settings.php';
