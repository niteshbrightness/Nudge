<?php

use App\Contracts\ProjectSync\ProjectSourceInterface;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\ProjectSync\NormalizedProject;
use App\Services\ProjectSyncManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $tenant = Tenant::create(['id' => 'test-tenant', 'name' => 'Test Tenant']);
    tenancy()->initialize($tenant);
});

afterEach(function () {
    tenancy()->end();
});

test('guests are redirected to login on sync', function () {
    $this->post(route('projects.sync'))->assertRedirect(route('login'));
});

test('sync upserts projects from available sources and flashes success', function () {
    $user = User::factory()->create(['tenant_id' => 'test-tenant']);

    $source = new class implements ProjectSourceInterface
    {
        public function source(): string
        {
            return 'test';
        }

        public function isAvailable(): bool
        {
            return true;
        }

        public function fetchProjects(): array
        {
            return [
                new NormalizedProject('test', '1', 'Project Alpha', null, 'active', null),
                new NormalizedProject('test', '2', 'Project Beta', 'Desc', 'completed', 'https://example.com'),
            ];
        }
    };

    $manager = new ProjectSyncManager;
    $manager->register($source);
    $this->app->instance(ProjectSyncManager::class, $manager);

    $this->actingAs($user)
        ->post(route('projects.sync'))
        ->assertRedirect(route('projects.index'))
        ->assertSessionHas('success', '2 projects synced.');

    expect(Project::query()->count())->toBe(2);
    expect(Project::query()->where('external_id', '1')->first()->name)->toBe('Project Alpha');
});

test('sync with no available sources flashes 0 projects synced', function () {
    $user = User::factory()->create(['tenant_id' => 'test-tenant']);

    $source = new class implements ProjectSourceInterface
    {
        public function source(): string
        {
            return 'test';
        }

        public function isAvailable(): bool
        {
            return false;
        }

        public function fetchProjects(): array
        {
            return [];
        }
    };

    $manager = new ProjectSyncManager;
    $manager->register($source);
    $this->app->instance(ProjectSyncManager::class, $manager);

    $this->actingAs($user)
        ->post(route('projects.sync'))
        ->assertRedirect(route('projects.index'))
        ->assertSessionHas('success', '0 projects synced.');
});

test('sync with a throwing source flashes an error without re-throwing', function () {
    $user = User::factory()->create(['tenant_id' => 'test-tenant']);

    $source = new class implements ProjectSourceInterface
    {
        public function source(): string
        {
            return 'test';
        }

        public function isAvailable(): bool
        {
            return true;
        }

        public function fetchProjects(): array
        {
            throw new RuntimeException('API unreachable');
        }
    };

    $manager = new ProjectSyncManager;
    $manager->register($source);
    $this->app->instance(ProjectSyncManager::class, $manager);

    $this->actingAs($user)
        ->post(route('projects.sync'))
        ->assertRedirect(route('projects.index'))
        ->assertSessionHas('error');
});

test('sync combines totals from multiple sources', function () {
    $user = User::factory()->create(['tenant_id' => 'test-tenant']);

    $makeSource = fn (string $sourceName, array $projects) => new class($sourceName, $projects) implements ProjectSourceInterface
    {
        public function __construct(private string $sourceName, private array $projects) {}

        public function source(): string
        {
            return $this->sourceName;
        }

        public function isAvailable(): bool
        {
            return true;
        }

        public function fetchProjects(): array
        {
            return $this->projects;
        }
    };

    $manager = new ProjectSyncManager;
    $manager->register($makeSource('source_a', [
        new NormalizedProject('source_a', '1', 'A1', null, 'active', null),
    ]));
    $manager->register($makeSource('source_b', [
        new NormalizedProject('source_b', '1', 'B1', null, 'active', null),
        new NormalizedProject('source_b', '2', 'B2', null, 'active', null),
    ]));
    $this->app->instance(ProjectSyncManager::class, $manager);

    $this->actingAs($user)
        ->post(route('projects.sync'))
        ->assertSessionHas('success', '3 projects synced.');

    expect(Project::query()->count())->toBe(3);
});
