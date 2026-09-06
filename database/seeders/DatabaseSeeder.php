<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database for local development.
     *
     * Pilot School + Trust demo data cover Epics 1–2 (Roles, Schools, Pupils, Needs, Import-ready MIS keys).
     * Playwright uses {@see E2eSeeder} separately via `npm run test:e2e:prepare`.
     */
    public function run(): void
    {
        $this->call([
            DemoPilotSeeder::class,
            DemoTrustSeeder::class,
        ]);
    }
}
