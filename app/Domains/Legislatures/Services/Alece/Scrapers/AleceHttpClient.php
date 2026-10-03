<?php

namespace App\Domains\Legislatures\Services\Alece\Scrapers;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class AleceHttpClient
{
    protected int $timeout = 30;

    protected int $retryTimes = 3;

    protected int $retryDelay = 500;

    /**
     * Faz uma requisição GET e retorna o conteúdo.
     */
    public function get(string $url): string
    {
        return $this->request($url)->body();
    }

    /**
     * Faz uma requisição GET.
     */
    public function request(string $url): Response
    {
        return Http::timeout($this->timeout)
            ->retry($this->retryTimes, $this->retryDelay)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/154.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8',
                'Cache-Control' => 'no-cache',
            ])
            ->get($url)
            ->throw();
    }
}