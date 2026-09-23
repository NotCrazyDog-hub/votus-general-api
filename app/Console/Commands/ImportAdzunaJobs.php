<?php

namespace App\Console\Commands;

use App\Models\Opportunity;
use App\Services\Jobs\AdzunaProvider;
use Illuminate\Console\Command;

class ImportAdzunaJobs extends Command
{
    protected $signature =
        'jobs:import-adzuna {--pages=2}';

    protected $description =
        'Importa vagas da Adzuna para o banco de dados';


    public function handle(
        AdzunaProvider $adzuna
    ): int {

        /*
        |--------------------------------------------------------------------------
        | Pesquisas que queremos fazer
        |--------------------------------------------------------------------------
        */

        $searches = [

            'jovem aprendiz',

            'estágio',

            'primeiro emprego',

        ];


        $pages = max(
            1,
            (int) $this->option('pages')
        );


        $total = 0;


        foreach ($searches as $search) {

            $this->info(
                "Buscando: {$search}"
            );


            for (
                $page = 1;
                $page <= $pages;
                $page++
            ) {

                $jobs = $adzuna->search(
                    $search,
                    'Ceará',
                    $page
                );


                foreach ($jobs as $job) {

                    /*
                    |--------------------------------------------------------------------------
                    | Ignora resultado sem ID
                    |--------------------------------------------------------------------------
                    */

                    if (
                        empty(
                            $job['external_id']
                        )
                    ) {
                        continue;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Salva ou atualiza
                    |--------------------------------------------------------------------------
                    */

                    Opportunity::updateOrCreate(

                        [
                            'source' =>
                                'adzuna',

                            'external_id' =>
                                $job['external_id'],
                        ],

                        [
                            'opportunity_type' =>
                                $this->detectType(
                                    $job['title'],
                                    $job['description']
                                ),

                            'title' =>
                                $job['title'],

                            'company' =>
                                $job['company'],

                            'description' =>
                                $job['description'],

                            'location' =>
                                $job['location'],

                            'location_area' =>
                                $job['location_area'],

                            'latitude' =>
                                $job['latitude'],

                            'longitude' =>
                                $job['longitude'],

                            'category' =>
                                $job['category'],

                            'contract_type' =>
                                $job['contract_type'],

                            'contract_time' =>
                                $job['contract_time'],

                            'salary_min' =>
                                $job['salary_min'],

                            'salary_max' =>
                                $job['salary_max'],

                            'external_url' =>
                                $job['url'],

                            'published_at' =>
                                $job['published_at'],

                            'last_seen_at' =>
                                now(),

                            'is_active' =>
                                true,
                        ]
                    );


                    $total++;
                }
            }
        }


        $this->info(
            "{$total} vagas processadas."
        );


        return self::SUCCESS;
    }


    private function detectType(
        ?string $title,
        ?string $description
    ): string {

        $text = mb_strtolower(
            ($title ?? '')
            .' '.
            ($description ?? '')
        );


        if (
            str_contains(
                $text,
                'aprendiz'
            )
        ) {
            return 'jovem_aprendiz';
        }


        if (
            str_contains(
                $text,
                'estágio'
            )
            ||
            str_contains(
                $text,
                'estagio'
            )
        ) {
            return 'estagio';
        }


        return 'emprego';
    }
}