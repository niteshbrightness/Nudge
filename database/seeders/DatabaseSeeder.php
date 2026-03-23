<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TimezoneSeeder::class,
        ]);

        $tenant = Tenant::firstOrCreate(
            ['id' => 'test-tenant'],
            ['name' => 'Test Tenant'],
        );

        User::firstOrCreate(
            ['email' => 'test@yopmail.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('Test@123'),
                'tenant_id' => $tenant->id,
                'email_verified_at' => now(),
            ],
        );
    }
}
