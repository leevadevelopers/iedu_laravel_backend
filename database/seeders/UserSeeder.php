<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin User',
                'identifier' => 'admin@admin.com',
                'type' => 'email',
                'password' => Hash::make('12345678'),
                'verified_at' => now(),
                'is_active' => true,
                'settings' => json_encode(['theme' => 'dark', 'notifications' => true])
            ],
            [
                'name' => 'John Doe',
                'identifier' => 'john.doe@acme.com',
                'type' => 'email',
                'password' => Hash::make('password123'),
                'verified_at' => now(),
                'is_active' => true,
                'settings' => json_encode(['theme' => 'light', 'notifications' => true])
            ],
            [
                'name' => 'Jane Smith',
                'identifier' => 'jane.smith@techstart.com',
                'type' => 'email',
                'password' => Hash::make('password123'),
                'verified_at' => now(),
                'is_active' => true,
                'settings' => json_encode(['theme' => 'auto', 'notifications' => false])
            ],
            [
                'name' => 'Bob Wilson',
                'identifier' => 'bob.wilson@global.com',
                'type' => 'email',
                'password' => Hash::make('password123'),
                'verified_at' => now(),
                'is_active' => true,
                'settings' => json_encode(['theme' => 'dark', 'notifications' => true])
            ],
            [
                'name' => 'Alice Johnson',
                'identifier' => 'alice.johnson@innovation.com',
                'type' => 'email',
                'password' => Hash::make('password123'),
                'verified_at' => now(),
                'is_active' => true,
                'settings' => json_encode(['theme' => 'light', 'notifications' => true])
            ],
            [
                'name' => 'Charlie Brown',
                'identifier' => '+1234567890',
                'type' => 'phone',
                'password' => Hash::make('password123'),
                'verified_at' => now(),
                'is_active' => true,
                'settings' => json_encode(['theme' => 'auto', 'notifications' => true])
            ],
            [
                'name' => 'Diana Prince',
                'identifier' => 'diana.prince@acme.com',
                'type' => 'email',
                'password' => Hash::make('password123'),
                'verified_at' => now(),
                'is_active' => true,
                'settings' => json_encode(['theme' => 'dark', 'notifications' => false])
            ],
            [
                'name' => 'Eve Adams',
                'identifier' => 'eve.adams@techstart.com',
                'type' => 'email',
                'password' => Hash::make('password123'),
                'verified_at' => now(),
                'is_active' => true,
                'settings' => json_encode(['theme' => 'light', 'notifications' => true])
            ]
        ];

        foreach ($users as $userData) {
            User::create($userData);
        }
    }
} 