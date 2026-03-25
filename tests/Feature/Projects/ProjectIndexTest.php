<?php

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $tenant = Tenant::create(['id' => 'test-tenant', 'name' => 'Test Tenant']);
    tenancy()->initialize($tenant);
    $this->user = User::factory()->create(['tenant_id' => 'test-tenant']);
});

afterEach(function () {
    tenancy()->end();
});

test('guests are redirected to login on index', function () {
    $this->get(route('projects.index'))->assertRedirect(route('login'));
});

test('index defaults to filtering active projects', function () {
    Project::factory()->create(['name' => 'Active Project', 'status' => 'active']);
    Project::factory()->create(['name' => 'Completed Project', 'status' => 'completed']);
    Project::factory()->create(['name' => 'On Hold Project', 'status' => 'on_hold']);

    $response = $this->actingAs($this->user)->get(route('projects.index'));

    $response->assertInertia(
        fn ($page) => $page
            ->component('projects/index')
            ->where('filters.status', 'active')
            ->has('projects.data', 1)
            ->where('projects.data.0.name', 'Active Project'),
    );
});

test('index can be filtered to show completed projects', function () {
    Project::factory()->create(['status' => 'active']);
    Project::factory()->create(['name' => 'Done Project', 'status' => 'completed']);

    $response = $this->actingAs($this->user)->get(route('projects.index', ['status' => 'completed']));

    $response->assertInertia(
        fn ($page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.status', 'completed'),
    );
});

test('index sorts projects by name ascending', function () {
    Project::factory()->create(['name' => 'Zebra Project', 'status' => 'active']);
    Project::factory()->create(['name' => 'Alpha Project', 'status' => 'active']);
    Project::factory()->create(['name' => 'Middle Project', 'status' => 'active']);

    $response = $this->actingAs($this->user)->get(route('projects.index', ['sort_by' => 'name', 'sort_dir' => 'asc']));

    $response->assertInertia(
        fn ($page) => $page
            ->where('projects.data.0.name', 'Alpha Project')
            ->where('projects.data.1.name', 'Middle Project')
            ->where('projects.data.2.name', 'Zebra Project'),
    );
});

test('index sorts projects by name descending', function () {
    Project::factory()->create(['name' => 'Zebra Project', 'status' => 'active']);
    Project::factory()->create(['name' => 'Alpha Project', 'status' => 'active']);

    $response = $this->actingAs($this->user)->get(route('projects.index', ['sort_by' => 'name', 'sort_dir' => 'desc']));

    $response->assertInertia(
        fn ($page) => $page
            ->where('projects.data.0.name', 'Zebra Project')
            ->where('projects.data.1.name', 'Alpha Project'),
    );
});

test('index ignores invalid sort column', function () {
    Project::factory()->count(2)->create(['status' => 'active']);

    $this->actingAs($this->user)
        ->get(route('projects.index', ['sort_by' => 'password', 'sort_dir' => 'asc']))
        ->assertOk();
});
