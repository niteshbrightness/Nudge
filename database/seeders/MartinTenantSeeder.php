<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MartinTenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['id' => 'constructiveroots'],
            ['name' => 'Constructive Roots'],
        );

        User::firstOrCreate(
            ['email' => 'martin@constructiveroots.com'],
            [
                'name' => 'Martin Masin',
                'password' => Hash::make('Admin@123'),
                'tenant_id' => $tenant->id,
                'email_verified_at' => now(),
            ],
        );
    }
}
