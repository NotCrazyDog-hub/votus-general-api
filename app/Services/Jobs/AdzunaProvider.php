<?php

namespace App\Services\Jobs;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class AdzunaProvider
{
    public function search(
        string $term,
        string $location = 'Ceará',
        int $page = 1
    ): array {

        $appId = config('jobs.adzuna.app_id');
        $appKey = config('jobs.adzuna.app_key');
        $country = config('jobs.adzuna.country', 'br');


        if (!$appId || !$appKey) {
            throw new RuntimeException(
                'Credenciais da Adzuna não configuradas.'
            );
        }


        $response = Http::acceptJson()
            ->timeout(15)
            ->retry(2, 500)
            ->get(
                "https://api.adzuna.com/v1/api/jobs/{$country}/search/{$page}",
                [
                    'app_id' => $appId,
                    'app_key' => $appKey,

                    'results_per_page' => 20,

                    'what' => $term,

                    'where' => $location,

                    'sort_by' => 'date',
                ]
            );


        $response->throw();


        $jobs = $response->json(
            'results',
            []
        );


        return collect($jobs)
            ->map(function (array $job) {

                return [

                    'source' => 'adzuna',

                    'external_id' =>
                        (string) ($job['id'] ?? ''),

                    'title' =>
                        $job['title'] ?? null,

                    'company' =>
                        data_get(
                            $job,
                            'company.display_name'
                        ),

                    'description' =>
                        $job['description'] ?? null,

                    'location' =>
                        data_get(
                            $job,
                            'location.display_name'
                        ),

                    'location_area' =>
                        data_get(
                            $job,
                            'location.area',
                            []
                        ),

                    'latitude' =>
                        $job['latitude'] ?? null,

                    'longitude' =>
                        $job['longitude'] ?? null,

                    'category' =>
                        data_get(
                            $job,
                            'category.label'
                        ),

                    'contract_type' =>
                        $job['contract_type'] ?? null,

                    'contract_time' =>
                        $job['contract_time'] ?? null,

                    'salary_min' =>
                        $job['salary_min'] ?? null,

                    'salary_max' =>
                        $job['salary_max'] ?? null,

                    'url' =>
                        $job['redirect_url'] ?? null,

                    'published_at' =>
                        $job['created'] ?? null,
                ];
            })
            ->values()
            ->all();
    }
}