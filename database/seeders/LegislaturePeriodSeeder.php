<?php

namespace Database\Seeders;

use App\Domains\Legislatures\Models\LegislaturePeriod;
use Illuminate\Database\Seeder;

class LegislaturePeriodSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Câmara dos Deputados
        |--------------------------------------------------------------------------
        */

        $lowerHousePeriods = [
            [
                'legislature_number' => 57,
                'chamber' => 'lower_house',
                'starts_at' => '2023-02-01',
                'ends_at' => '2027-01-31',
            ],
            [
                'legislature_number' => 56,
                'chamber' => 'lower_house',
                'starts_at' => '2019-02-01',
                'ends_at' => '2023-01-31',
            ],
            [
                'legislature_number' => 55,
                'chamber' => 'lower_house',
                'starts_at' => '2015-02-01',
                'ends_at' => '2019-01-31',
            ],
            [
                'legislature_number' => 54,
                'chamber' => 'lower_house',
                'starts_at' => '2011-02-01',
                'ends_at' => '2015-01-31',
            ],
            [
                'legislature_number' => 53,
                'chamber' => 'lower_house',
                'starts_at' => '2007-02-01',
                'ends_at' => '2011-01-31',
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | Senado Federal
        |--------------------------------------------------------------------------
        |
        | A numeração da legislatura é a mesma da Câmara.
        |
        */

        $senatePeriods = [
            [
                'legislature_number' => 57,
                'chamber' => 'senate',
                'starts_at' => '2023-02-01',
                'ends_at' => '2027-01-31',
            ],
            [
                'legislature_number' => 56,
                'chamber' => 'senate',
                'starts_at' => '2019-02-01',
                'ends_at' => '2023-01-31',
            ],
            [
                'legislature_number' => 55,
                'chamber' => 'senate',
                'starts_at' => '2015-02-01',
                'ends_at' => '2019-01-31',
            ],
            [
                'legislature_number' => 54,
                'chamber' => 'senate',
                'starts_at' => '2011-02-01',
                'ends_at' => '2015-01-31',
            ],
            [
                'legislature_number' => 53,
                'chamber' => 'senate',
                'starts_at' => '2007-02-01',
                'ends_at' => '2011-01-31',
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | Assembleia Legislativa
        |--------------------------------------------------------------------------
        |
        | Atualmente estamos considerando a 31ª legislatura estadual.
        |
        */

        $stateHousePeriods = [
            [
                'legislature_number' => 31,
                'chamber' => 'state_house',
                'starts_at' => '2023-02-01',
                'ends_at' => '2027-01-31',
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | Persistência
        |--------------------------------------------------------------------------
        */

        $periods = array_merge(
            $lowerHousePeriods,
            $senatePeriods,
            $stateHousePeriods
        );

        foreach ($periods as $period) {
            LegislaturePeriod::updateOrCreate(
                [
                    'legislature_number' => $period['legislature_number'],
                    'chamber' => $period['chamber'],
                ],
                [
                    'starts_at' => $period['starts_at'],
                    'ends_at' => $period['ends_at'],
                ]
            );
        }
    }
}