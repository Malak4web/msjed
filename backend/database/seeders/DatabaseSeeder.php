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
            RolesAndPermissionsSeeder::class,
            LookupsSeeder::class,
            DefaultUsersSeeder::class,
        ]);

        if (\App\Models\ExpenseRequest::count() === 0 && \App\Models\Income::count() === 0) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
