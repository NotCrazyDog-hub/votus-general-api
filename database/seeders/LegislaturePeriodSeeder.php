<?php

namespace Database\Seeders;

use App\Models\LegislaturePeriod;
use Illuminate\Database\Seeder;

class LegislaturePeriodSeeder extends Seeder
{
    public function run(): void
    {
        $periods = [
            ['legislature_number' => 57, 'starts_at' => '2023-02-01', 'ends_at' => '2027-01-31'],
            ['legislature_number' => 56, 'starts_at' => '2019-02-01', 'ends_at' => '2023-01-31'],
            ['legislature_number' => 55, 'starts_at' => '2015-02-01', 'ends_at' => '2019-01-31'],
            ['legislature_number' => 54, 'starts_at' => '2011-02-01', 'ends_at' => '2015-01-31'],
            ['legislature_number' => 53, 'starts_at' => '2007-02-01', 'ends_at' => '2011-01-31'],
        ];

        foreach ($periods as $period) {
            LegislaturePeriod::updateOrCreate(
                ['legislature_number' => $period['legislature_number']],
                $period
            );
        }
    }
}