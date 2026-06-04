<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class FullSystemSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->warn('FullSystemSeeder is disabled. Random demo data is no longer seeded.');
    }
}
