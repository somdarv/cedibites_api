<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            BranchSeeder::class,
            EmployeeSeeder::class,
            MenuSeeder::class,
            CustomerSeeder::class,
            OrderSeeder::class,
            CartSeeder::class,
        ]);
    }
}
