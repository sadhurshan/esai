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
            CurrenciesSeeder::class,
            PlansSeeder::class,
            UomSeeder::class,
            RoleTemplateSeeder::class,
            DevTenantSeeder::class,
        ]);
    }
}
