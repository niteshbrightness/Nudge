<?php

use App\ProjectSync\NormalizedProject;
use App\ProjectSync\Sources\ActiveCollabSource;
use App\Services\ActiveCollabService;

test('isAvailable returns false when ActiveCollabService is not configured', function () {
    $service = new ActiveCollabService(baseUrl: '', token: '');
    $source = new ActiveCollabSource($service);

    expect($source->isAvailable())->toBeFalse();
});

test('isAvailable returns true when ActiveCollabService is configured', function () {
    $service = new ActiveCollabService(baseUrl: 'https://example.com', token: 'secret');
    $source = new ActiveCollabSource($service);

    expect($source->isAvailable())->toBeTrue();
});

test('source returns activecollab', function () {
    $service = new ActiveCollabService;
    $source = new ActiveCollabSource($service);

    expect($source->source())->toBe('activecollab');
});

test('fetchProjects maps raw data to NormalizedProject DTOs', function () {
    $rawProjects = [
        ['id' => 1, 'name' => 'Alpha', 'body' => 'Description', 'is_completed' => false, 'url' => 'https://example.com/1'],
        ['id' => 2, 'name' => 'Beta', 'is_completed' => true],
    ];

    $service = Mockery::mock(ActiveCollabService::class);
    $service->shouldReceive('fetchProjects')->once()->andReturn($rawProjects);

    $source = new ActiveCollabSource($service);
    $results = $source->fetchProjects();

    expect($results)->toHaveCount(2);
    expect($results[0])->toBeInstanceOf(NormalizedProject::class);
    expect($results[0]->source)->toBe('activecollab');
    expect($results[0]->externalId)->toBe('1');
    expect($results[0]->name)->toBe('Alpha');
    expect($results[0]->description)->toBe('Description');
    expect($results[0]->status)->toBe('active');
    expect($results[0]->url)->toBe('https://example.com/1');

    expect($results[1]->externalId)->toBe('2');
    expect($results[1]->status)->toBe('completed');
    expect($results[1]->description)->toBeNull();
    expect($results[1]->url)->toBeNull();
});
