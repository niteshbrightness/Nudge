<?php

use App\Models\Client;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Timezone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $tenant = Tenant::create(['id' => 'test-tenant', 'name' => 'Test Tenant']);
    tenancy()->initialize($tenant);

    Timezone::create([
        'name' => 'UTC',
        'label' => 'UTC (UTC+00:00)',
        'offset' => '+00:00',
        'offset_minutes' => 0,
    ]);
});

afterEach(function () {
    tenancy()->end();
});

test('guests are redirected to login', function () {
    $project = Project::factory()->create();

    $this->put(route('projects.update', $project), [])->assertRedirect(route('login'));
});

test('can assign a client to a project', function () {
    $user = User::factory()->create(['tenant_id' => 'test-tenant']);
    $project = Project::factory()->create(['client_id' => null, 'status' => 'active']);
    $client = Client::factory()->create();

    $this->actingAs($user)
        ->put(route('projects.update', $project), ['client_id' => $client->id, 'status' => 'active'])
        ->assertRedirect(route('projects.show', $project));

    expect($project->fresh()->client_id)->toBe($client->id);
});

test('can unassign a client from a project', function () {
    $user = User::factory()->create(['tenant_id' => 'test-tenant']);
    $client = Client::factory()->create();
    $project = Project::factory()->create(['client_id' => $client->id, 'status' => 'active']);

    $this->actingAs($user)
        ->put(route('projects.update', $project), ['client_id' => null, 'status' => 'active'])
        ->assertRedirect(route('projects.show', $project));

    expect($project->fresh()->client_id)->toBeNull();
});

test('can update project status', function () {
    $user = User::factory()->create(['tenant_id' => 'test-tenant']);
    $project = Project::factory()->create(['status' => 'active']);

    $this->actingAs($user)
        ->put(route('projects.update', $project), ['client_id' => null, 'status' => 'on_hold'])
        ->assertRedirect(route('projects.show', $project));

    expect($project->fresh()->status)->toBe('on_hold');
});

test('invalid status returns a validation error', function () {
    $user = User::factory()->create(['tenant_id' => 'test-tenant']);
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->put(route('projects.update', $project), ['client_id' => null, 'status' => 'invalid_status'])
        ->assertSessionHasErrors('status');
});

test('invalid client_id returns a validation error', function () {
    $user = User::factory()->create(['tenant_id' => 'test-tenant']);
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->put(route('projects.update', $project), ['client_id' => 99999, 'status' => 'active'])
        ->assertSessionHasErrors('client_id');
});
