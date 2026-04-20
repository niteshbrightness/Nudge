<?php

namespace App\Http\Controllers\SmsHistory;

use App\Contracts\Repositories\NotificationLogRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SmsHistoryController extends Controller
{
    public function __construct(
        private readonly NotificationLogRepositoryInterface $logs
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status', 'project_id']);

        return Inertia::render('sms-history/index', [
            'logs' => $this->logs->paginate(20, $filters),
            'filters' => $filters,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
