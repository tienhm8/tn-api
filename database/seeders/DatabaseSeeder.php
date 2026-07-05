<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            ServiceSeeder::class,
            SettingSeeder::class,
        ]);

        $accounts = [
            ['name' => 'Quản trị hệ thống', 'email' => 'admin@tn.local', 'role' => 'admin'],
            ['name' => 'Marketing 01', 'email' => 'marketing@tn.local', 'role' => 'marketing'],
            ['name' => 'Sale 01', 'email' => 'sale1@tn.local', 'role' => 'sale'],
            ['name' => 'Sale 02', 'email' => 'sale2@tn.local', 'role' => 'sale'],
        ];

        foreach ($accounts as $account) {
            $user = User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => 'password',
                    'is_active' => true,
                ],
            );

            $user->syncRoles($account['role']);
        }
    }
}
