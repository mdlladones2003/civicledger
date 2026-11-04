<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Synthetic demo data only. The demo seeder refuses to run in production.
        $this->call(CivicLedgerSeeder::class);
    }
}
