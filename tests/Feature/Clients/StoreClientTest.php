<?php

use App\Models\Client;
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

function storeClientPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Acme Corp',
        'phone' => '+1234567890',
        'timezone_id' => Timezone::first()->id,
        'notes' => null,
    ], $overrides);
}

test('guests are redirected to login', function () {
    $this->post(route('clients.store'), storeClientPayload())->assertRedirect(route('login'));
});

test('authenticated user can create a client', function () {
    $user = User::factory()->create(['tenant_id' => 'test-tenant']);

    $this->actingAs($user)
        ->post(route('clients.store'), storeClientPayload())
        ->assertRedirect(route('clients.index'));

    expect(Client::where('name', 'Acme Corp')->exists())->toBeTrue();
});

test('phone must be in E.164 format on store', function (string $phone) {
    $user = User::factory()->create(['tenant_id' => 'test-tenant']);

    $this->actingAs($user)
        ->post(route('clients.store'), storeClientPayload(['phone' => $phone]))
        ->assertSessionHasErrors('phone');
})->with([
    'missing plus prefix' => '17096789000',
    'letters in number' => '+1abc5550000',
    'plus only' => '+',
    'empty' => '',
]);

test('valid E.164 phone is accepted on store', function () {
    $user = User::factory()->create(['tenant_id' => 'test-tenant']);

    $this->actingAs($user)
        ->post(route('clients.store'), storeClientPayload(['phone' => '+17096789000']))
        ->assertRedirect(route('clients.index'));

    expect(Client::where('phone', '+17096789000')->exists())->toBeTrue();
});
