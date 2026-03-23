<?php

namespace App\Http\Controllers\Projects;

use App\Contracts\Repositories\ProjectRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Models\Client;
use App\Models\Project;
use App\Services\ActiveCollabService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projects,
        private readonly ActiveCollabService $activeCollabService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('projects/index', [
            'projects' => $this->projects->paginate(),
        ]);
    }

    public function show(Project $project): Response
    {
        return Inertia::render('projects/show', [
            'project' => $this->projects->find($project->id),
        ]);
    }

    public function edit(Project $project): Response
    {
        return Inertia::render('projects/edit', [
            'project' => $this->projects->find($project->id),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->projects->update($project, ['client_id' => $request->client_id]);

        return redirect()->route('projects.show', $project)->with('success', 'Client assigned successfully.');
    }

    public function sync(): RedirectResponse
    {
        try {
            $activecollabProjects = $this->activeCollabService->fetchProjects();
        } catch (\Throwable $e) {
            return redirect()->route('projects.index')->with('error', 'Sync failed: '.$e->getMessage());
        }

        foreach ($activecollabProjects as $acProject) {
            $this->projects->upsertFromActiveCollab([
                'activecollab_id' => $acProject['id'],
                'name' => $acProject['name'],
                'description' => $acProject['body'] ?? null,
                'status' => $acProject['is_completed'] ? 'completed' : 'active',
                'url' => $acProject['url'] ?? null,
            ]);
        }

        return redirect()->route('projects.index')->with('success', count($activecollabProjects).' projects synced from ActiveCollab.');
    }
}
