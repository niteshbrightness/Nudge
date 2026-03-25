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

function clientPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Acme Corp',
        'phone' => '+1234567890',
        'timezone_id' => Timezone::first()->id,
        'notes' => null,
    ], $overrides);
}

test('guests are redirected to login', function () {
    $client = Client::factory()->create();

    $this->put(route('clients.update', $client), clientPayload())->assertRedirect(route('login'));
});

test('can update client details without changing projects', function () {
    $user = User::factory()->create(['tenant_id' => 'test-tenant']);
    $client = Client::factory()->create();

    $this->actingAs($user)
        ->put(route('clients.update', $client), clientPayload(['name' => 'Updated Name']))
        ->assertRedirect(route('clients.index'));

    expect($client->fresh()->name)->toBe('Updated Name');
});

test('can assign projects to a client', function () {
    $user = User::factory()->create(['tenant_id' => 'test-tenant']);
    $client = Client::factory()->create();
    $project = Project::factory()->create(['client_id' => null]);

    $this->actingAs($user)
        ->put(route('clients.update', $client), clientPayload(['project_ids' => [$project->id]]))
        ->assertRedirect(route('clients.index'));

    expect($project->fresh()->client_id)->toBe($client->id);
});

test('can remove a project from a client', function () {
    $user = User::factory()->create(['tenant_id' => 'test-tenant']);
    $client = Client::factory()->create();
    $kept = Project::factory()->create(['client_id' => $client->id]);
    $removed = Project::factory()->create(['client_id' => $client->id]);

    $this->actingAs($user)
        ->put(route('clients.update', $client), clientPayload(['project_ids' => [$kept->id]]))
        ->assertRedirect(route('clients.index'));

    expect($kept->fresh()->client_id)->toBe($client->id);
    expect($removed->fresh()->client_id)->toBeNull();
});

test('can clear all projects from a client', function () {
    $user = User::factory()->create(['tenant_id' => 'test-tenant']);
    $client = Client::factory()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);

    $this->withoutExceptionHandling()->actingAs($user)
        ->put(route('clients.update', $client), clientPayload(['project_ids' => ['']]))
        ->assertRedirect(route('clients.index'));

    expect($project->fresh()->client_id)->toBeNull();
});

test('cannot steal a project already assigned to another client', function () {
    $user = User::factory()->create(['tenant_id' => 'test-tenant']);
    $client = Client::factory()->create();
    $otherClient = Client::factory()->create();
    $project = Project::factory()->create(['client_id' => $otherClient->id]);

    $this->actingAs($user)
        ->put(route('clients.update', $client), clientPayload(['project_ids' => [$project->id]]))
        ->assertRedirect(route('clients.index'));

    expect($project->fresh()->client_id)->toBe($otherClient->id);
});

test('invalid project_id returns a validation error', function () {
    $user = User::factory()->create(['tenant_id' => 'test-tenant']);
    $client = Client::factory()->create();

    $this->actingAs($user)
        ->put(route('clients.update', $client), clientPayload(['project_ids' => [99999]]))
        ->assertSessionHasErrors('project_ids.0');
});

test('phone must be in E.164 format on update', function (string $phone) {
    $user = User::factory()->create(['tenant_id' => 'test-tenant']);
    $client = Client::factory()->create();

    $this->actingAs($user)
        ->put(route('clients.update', $client), clientPayload(['phone' => $phone]))
        ->assertSessionHasErrors('phone');
})->with([
    'missing plus prefix' => '917096789000',
    'letters in number' => '+1abc5550000',
    'plus only' => '+',
    'empty' => '',
]);

test('valid E.164 phone is accepted on update', function () {
    $user = User::factory()->create(['tenant_id' => 'test-tenant']);
    $client = Client::factory()->create();

    $this->actingAs($user)
        ->put(route('clients.update', $client), clientPayload(['phone' => '+917096789000']))
        ->assertRedirect(route('clients.index'));

    expect($client->fresh()->phone)->toBe('+917096789000');
});
