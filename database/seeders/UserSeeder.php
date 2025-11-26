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
                'password' => Hash::make('123456789'),
                'verified_at' => now(),
                'role_id' => 1,
                'is_active' => true,
                'profile_photo_path' => 'https://source.unsplash.com/128x128/?face,portrait,person&sig=1',
                'settings' => json_encode(['theme' => 'dark', 'notifications' => true])
            ],

        ];

        foreach ($users as $userData) {
            User::create($userData);
        }
    }
}
