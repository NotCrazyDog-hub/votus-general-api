<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class N8nContentService
{
    public function generate(
        array $data
    ): array {

        $url = config(
            'services.n8n.generate_explanation_url'
        );

        if (!$url) {
            throw new RuntimeException(
                'A URL do n8n está vazia.'
            );
        }

        $response = Http::acceptJson()
            ->asJson()
            ->timeout(180)
            ->post(
                $url,
                $data
            );

        if (!$response->successful()) {
            throw new RuntimeException(
                'Erro n8n HTTP '
                . $response->status()
                . ': '
                . $response->body()
            );
        }

        return $response->json();
    }
}