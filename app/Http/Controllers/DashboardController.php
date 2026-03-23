<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\WebhookEvent;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('dashboard', [
            'stats' => [
                'clients' => Client::query()->count(),
                'projects' => Project::query()->count(),
                'webhookEvents' => WebhookEvent::query()->count(),
                'recentEvents' => WebhookEvent::query()
                    ->with('project')
                    ->latest('received_at')
                    ->limit(5)
                    ->get(),
            ],
        ]);
    }
}
