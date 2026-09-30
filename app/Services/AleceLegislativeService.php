<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use Carbon\Carbon;

class AleceLegislativeService
{
    protected string $baseUrl = 'https://www2.al.ce.gov.br/legislativo/proposicoes';

    protected array $descricaoTypes = [
        1 => 'PL',
        5 => 'PEC',
    ];

    /**
     * Busca todas as proposições (PL e PEC) de um parlamentar por nome,
     * combinando os dois tipos e os dois status de busca (D e T).
     *
     * @return array<int, array> lista de proposições únicas
     */
    public function getBillsByAuthorName(string $parliamentaryName): array
    {
        $searchToken = base64_encode(mb_strtoupper($parliamentaryName));
        $results = [];

        foreach ($this->descricaoTypes as $descricao => $type) {
            foreach (['D', 'T'] as $opcao) {
                foreach ($this->fetchAllPages($descricao, $opcao, $searchToken) as $row) {
                    $row['type'] = $type;
                    $row['status_tramitando'] = $opcao === 'T';

                    // Dedup por chave legislatura+codigo — mesma proposição pode
                    // aparecer nas duas buscas (D e T) se o filtro não for exclusivo.
                    $key = "{$row['legislature']}_{$row['codigo']}";
                    $results[$key] = $row;
                }

                // Educado com o servidor do governo — evita martelar requisições.
                usleep(300_000);
            }
        }

        return array_values($results);
    }

    protected function fetchAllPages(int $descricao, string $opcao, string $searchToken): \Generator
    {
        $page = 1;
        $totalPages = 1;

        do {
            $response = Http::withOptions(['verify' => false])
                ->get("{$this->baseUrl}/busca_nova.php", [
                    'descricao' => $descricao,
                    'nome' => 'atual_legislatura', // não parece restringir o resultado, mas mantém o padrão da URL
                    'opcao' => $opcao,
                    'escolha' => 'autor',
                    'pesquisa' => $searchToken,
                    'pagina' => $page,
                ]);

            if ($response->failed()) {
                throw new \RuntimeException("Falha ao buscar proposições (descricao={$descricao}, opcao={$opcao}, página={$page}): " . $response->status());
            }

            $html = $response->body();
            $crawler = new Crawler($html);

            // Extrai "Exibindo página X de Y" pra saber quando parar.
            if (preg_match('/p[áa]gina\s+\d+\s+de\s+(\d+)/ui', $html, $matches)) {
                $totalPages = (int) $matches[1];
            }

            foreach ($this->parseRows($crawler) as $row) {
                yield $row;
            }

            $page++;
            usleep(200_000);
        } while ($page <= $totalPages);
    }

    /**
     * ATENÇÃO: essa extração assume uma estrutura genérica de <table>/<tr>/<td>
     * com um link contendo "ver_nova.php" em algum <td> da linha. Isso foi
     * inferido a partir do conteúdo renderizado, não do HTML bruto — se não
     * bater exatamente com o markup real do site, ajuste os índices de
     * $cells->eq(N) abaixo conforme o que aparecer no dd($html) de teste.
     */
    protected function parseRows(Crawler $crawler): array
    {
        $rows = [];

        $crawler->filter('tbody tr')->each(function (Crawler $row) use (&$rows) {
            $detailLink = $row->filter('td.col-editar a[href*="ver_nova.php"]');

            if ($detailLink->count() === 0) {
                return;
            }

            $href = $detailLink->attr('href');
            parse_str((string) parse_url($href, PHP_URL_QUERY), $params);

            if (!isset($params['codigo'])) {
                return;
            }

            $legislature = isset($params['nome'])
                ? (int) filter_var($params['nome'], FILTER_SANITIZE_NUMBER_INT)
                : null;

            $rows[] = [
                'codigo' => $params['codigo'],
                'legislature' => $legislature,
                'bill_number' => trim($row->filter('td.col-proj')->text('')),
                'year' => trim($row->filter('td.col-ano')->text('')),
                'law_number' => trim($row->filter('td.col-lei')->text('')),
                'summary_short' => trim($row->filter('td.col-ementa')->text('')),
                'status_short' => trim($row->filter('td.col-status')->first()->text('')),
                'detail_url' => $this->resolveUrl($href),
            ];
        });

        return $rows;
    }

    protected function resolveUrl(string $href): string
    {
        return str_starts_with($href, 'http')
            ? $href
            : "{$this->baseUrl}/" . ltrim($href, '/');
    }

    /**
     * Busca os detalhes completos de uma proposição (data de entrada, ementa
     * completa, lei, status descritivo) na página ver_nova.php.
     */
    public function getBillDetail(string $detailUrl): array
    {
        $response = Http::withOptions(['verify' => false])->get($detailUrl);

        if ($response->failed()) {
            throw new \RuntimeException("Falha ao buscar detalhe da proposição: {$detailUrl} — " . $response->status());
        }

        $crawler = new Crawler($response->body());
        $text = $crawler->text('');

        $entrada = $this->extractField($text, 'Entrada:');
        $obs = $this->extractField($text, 'OBS:');

        $ementaLink = $crawler->filter('a[href*="tramit"]')->first();

        return [
            'presented_at' => $this->parseAleceDate($entrada),
            'summary' => $ementaLink->count() ? trim($ementaLink->text('')) : null,
            'summary_url' => $ementaLink->count() ? $this->resolveUrl($ementaLink->attr('href')) : null,
            'status_description' => $obs,
        ];
    }

    protected function extractField(string $text, string $label): ?string
    {
        if (preg_match('/' . preg_quote($label, '/') . '\s*([^\n]+)/u', $text, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    protected function parseAleceDate(?string $date): ?string
    {
        if (!$date) {
            return null;
        }

        try {
            // Formato "dd.mm.yy" (ex: "14.05.25")
            return Carbon::createFromFormat('d.m.y', $date)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }
}