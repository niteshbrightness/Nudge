<?php

namespace App\Http\Controllers\Clients;

use App\Contracts\Repositories\ClientRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\StoreClientRequest;
use App\Http\Requests\Clients\UpdateClientRequest;
use App\Models\Client;
use App\Models\Project;
use App\Models\Timezone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function __construct(private readonly ClientRepositoryInterface $clients) {}

    public function index(Request $request): Response
    {
        return Inertia::render('clients/index', [
            'clients' => $this->clients->paginate(15, $request->only(['search', 'project_id'])),
            'filters' => $request->only(['search', 'project_id']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('clients/create', [
            'timezones' => Timezone::query()->orderBy('offset_minutes')->get(['id', 'label', 'name']),
            'availableProjects' => Project::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $client = $this->clients->create($request->safe()->except('project_ids'));
        $projectIds = array_map('intval', array_filter($request->input('project_ids', []), fn ($v) => $v !== ''));
        $this->clients->syncProjects($client, $projectIds);

        return redirect()->route('clients.index')->with('success', 'Client created successfully.');
    }

    public function edit(Client $client): Response
    {
        return Inertia::render('clients/edit', [
            'client' => $client->load('timezone'),
            'timezones' => Timezone::query()->orderBy('offset_minutes')->get(['id', 'label', 'name']),
            'availableProjects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'selectedProjectIds' => $client->projects()->pluck('projects.id'),
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $this->clients->update($client, $request->safe()->except('project_ids'));
        $projectIds = array_map('intval', array_filter($request->input('project_ids', []), fn ($v) => $v !== ''));
        $this->clients->syncProjects($client, $projectIds);

        return redirect()->route('clients.index')->with('success', 'Client updated successfully.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->clients->delete($client);

        return redirect()->route('clients.index')->with('success', 'Client deleted successfully.');
    }
}
