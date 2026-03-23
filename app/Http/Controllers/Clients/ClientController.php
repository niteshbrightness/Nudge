<?php

namespace App\Http\Controllers\Clients;

use App\Contracts\Repositories\ClientRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\StoreClientRequest;
use App\Http\Requests\Clients\UpdateClientRequest;
use App\Models\Client;
use App\Models\Timezone;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function __construct(private readonly ClientRepositoryInterface $clients) {}

    public function index(): Response
    {
        return Inertia::render('clients/index', [
            'clients' => $this->clients->paginate(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('clients/create', [
            'timezones' => Timezone::query()->orderBy('offset_minutes')->get(['id', 'label', 'name']),
        ]);
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $this->clients->create($request->validated());

        return redirect()->route('clients.index')->with('success', 'Client created successfully.');
    }

    public function edit(Client $client): Response
    {
        return Inertia::render('clients/edit', [
            'client' => $client->load('timezone'),
            'timezones' => Timezone::query()->orderBy('offset_minutes')->get(['id', 'label', 'name']),
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $this->clients->update($client, $request->validated());

        return redirect()->route('clients.index')->with('success', 'Client updated successfully.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->clients->delete($client);

        return redirect()->route('clients.index')->with('success', 'Client deleted successfully.');
    }
}
