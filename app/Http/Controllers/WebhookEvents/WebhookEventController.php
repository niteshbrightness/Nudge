<?php

namespace App\Http\Controllers\WebhookEvents;

use App\Contracts\Repositories\WebhookEventRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebhookEventController extends Controller
{
    public function __construct(private readonly WebhookEventRepositoryInterface $webhookEvents) {}

    public function index(Request $request): Response
    {
        return Inertia::render('webhooks/index', [
            'events' => $this->webhookEvents->paginate(20, $request->only(['search', 'project_id'])),
            'filters' => $request->only(['search', 'project_id']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(WebhookEvent $webhookEvent): Response
    {
        return Inertia::render('webhooks/show', [
            'event' => $this->webhookEvents->find($webhookEvent->id),
        ]);
    }
}
