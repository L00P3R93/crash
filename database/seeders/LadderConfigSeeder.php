<?php

namespace Database\Seeders;

use App\Models\LadderConfig;
use Illuminate\Database\Seeder;

class LadderConfigSeeder extends Seeder
{
    public function run(): void
    {
        LadderConfig::query()->firstOrCreate(
            ['name' => 'default'],
            [
                'safe_ratio' => 1.10,
                'risky_ratio' => 2.00,
                'is_active' => true,
            ]
        );
    }
}
