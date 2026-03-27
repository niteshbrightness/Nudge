<?php

namespace App\Http\Controllers\Projects;

use App\Contracts\Repositories\ProjectRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Models\Client;
use App\Models\Project;
use App\Services\ProjectSyncManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projects,
        private readonly ProjectSyncManager $syncManager,
    ) {}

    public function index(Request $request): Response
    {
        $filters = array_merge(
            ['status' => 'active'],
            $request->only(['search', 'status', 'client_id', 'sort_by', 'sort_dir']),
        );

        return Inertia::render('projects/index', [
            'projects' => $this->projects->paginate(15, $filters),
            'filters' => $filters,
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
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
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->projects->update($project, [
            'status' => $request->status,
        ]);

        return redirect()->route('projects.show', $project)->with('success', 'Project updated successfully.');
    }

    public function sync(): RedirectResponse
    {
        $synced = 0;
        $errors = [];

        foreach ($this->syncManager->all() as $source) {
            if (! $source->isAvailable()) {
                continue;
            }

            try {
                $projects = $source->fetchProjects();

                foreach ($projects as $normalized) {
                    $this->projects->upsertFromSource($normalized);
                }

                $synced += count($projects);
            } catch (\Throwable $e) {
                $errors[] = $source->source().': '.$e->getMessage();
            }
        }

        if (! empty($errors)) {
            return redirect()->route('projects.index')
                ->with('error', 'Sync completed with errors: '.implode('; ', $errors));
        }

        return redirect()->route('projects.index')
            ->with('success', "{$synced} projects synced.");
    }
}
