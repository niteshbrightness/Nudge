<?php

namespace App\Http\Controllers\WebhookEvents;

use App\Contracts\Repositories\WebhookEventRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use Inertia\Inertia;
use Inertia\Response;

class WebhookEventController extends Controller
{
    public function __construct(private readonly WebhookEventRepositoryInterface $webhookEvents) {}

    public function index(): Response
    {
        return Inertia::render('webhooks/index', [
            'events' => $this->webhookEvents->paginate(),
        ]);
    }

    public function show(WebhookEvent $webhookEvent): Response
    {
        return Inertia::render('webhooks/show', [
            'event' => $this->webhookEvents->find($webhookEvent->id),
        ]);
    }
}
