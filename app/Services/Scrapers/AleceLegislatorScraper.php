<?php

namespace App\Services\Scrapers;

class AleceLegislatorScraper
{
    protected string $baseUrl = 'https://www.al.ce.gov.br';

    protected string $legislatorsUrl = 'https://www.al.ce.gov.br/deputados';

    public function __construct(
        protected AleceHttpClient $http,
        protected AleceLegislatorParser $parser
    ) {
    }

    /**
     * Descobre as URLs dos deputados no site da ALECE.
     */
    public function getLegislatorUrls(): array
    {
        $html = $this->http->get($this->legislatorsUrl);

        libxml_use_internal_errors(true);

        $document = new \DOMDocument();

        $document->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_NOERROR | LIBXML_NOWARNING
        );

        libxml_clear_errors();

        $xpath = new \DOMXPath($document);

        $results = [];

        /*
        * 31ª Legislatura.
        *
        * A página atual da ALECE está dentro da 31ª Legislatura.
        */
        $legislature = 31;

        /*
        * Localiza as seções:
        *
        * - Em Exercício e Licenciados
        * - Suplentes em Exercício
        */
        $headings = $xpath->query(
            '//h2[contains(@class, "main_page--title")]'
        );

        if ($headings === false) {
            return [];
        }

        foreach ($headings as $heading) {
            if (!$heading instanceof \DOMElement) {
                continue;
            }

            $title = trim(
                preg_replace(
                    '/\s+/u',
                    ' ',
                    $heading->textContent
                )
            );

            /*
            * Determina o tipo da seção.
            */
            if (
                mb_stripos(
                    $title,
                    'Suplentes em Exercício'
                ) !== false
            ) {
                $electoralStatus = 'suplente';
            } elseif (
                mb_stripos(
                    $title,
                    'Em Exercício e Licenciados'
                ) !== false
            ) {
                $electoralStatus = 'titular';
            } else {
                continue;
            }

            /*
            * O <h2> e os cards estão dentro do mesmo .row.
            */
            $container = $heading->parentNode;

            if (!$container instanceof \DOMElement) {
                continue;
            }

            $cards = $xpath->query(
                './/div[contains(concat(" ", normalize-space(@class), " "), " deputado_card ")]',
                $container
            );

            if ($cards === false) {
                continue;
            }

            foreach ($cards as $card) {
                if (!$card instanceof \DOMElement) {
                    continue;
                }

                $link = $xpath->query(
                    './/p[contains(@class, "deputado_card--nome")]//a[@href]',
                    $card
                );

                if ($link === false || $link->length === 0) {
                    continue;
                }

                $href = trim(
                    $link->item(0)->getAttribute('href')
                );

                $url = $this->absoluteUrl($href);

                if (!$url || !$this->isLegislatorUrl($url)) {
                    continue;
                }

                /*
                * A ALECE usa a classe "licenciado"
                * para deputados que não estão em exercício.
                */
                $class = ' ' . trim(
                    preg_replace(
                        '/\s+/u',
                        ' ',
                        $card->getAttribute('class')
                    )
                ) . ' ';

                $isLicensed = str_contains(
                    mb_strtolower($class),
                    ' licenciado '
                );

                $results[] = [
                    'url' => $url,
                    'status' => $isLicensed
                        ? 'inactive'
                        : 'active',
                    'electoral_status' => $electoralStatus,
                    'legislature' => $legislature,
                ];
            }
        }

        /*
        * Remove possíveis duplicados pelo URL.
        */
        $unique = [];

        foreach ($results as $result) {
            $unique[$result['url']] = $result;
        }

        return array_values($unique);
    }

    /**
     * Faz o scraping de um parlamentar.
     */
    public function scrape(string $url, array $context = []): array
    {
        $html = $this->http->get($url);

        return $this->parser->parse(
            $html,
            $url,
            $context
        );
    }


    /**
     * Faz o scraping de todos os parlamentares encontrados.
     */
    public function scrapeAll(): array
    {
        $legislators = [];

        foreach ($this->getLegislatorUrls() as $legislator) {
            try {
                $legislators[] = $this->scrape(
                    $legislator['url'],
                    $legislator
                );
            } catch (\Throwable $e) {
                report($e);

                continue;
            }
        }

        return $legislators;
    }


    /**
     * Verifica se uma URL parece ser de um parlamentar.
     */
    protected function isLegislatorUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (!$path) {
            return false;
        }

        $path = trim($path, '/');

        if (!preg_match('#^deputados/([^/]+)$#i', $path, $matches)) {
            return false;
        }

        return !in_array(
            mb_strtolower($matches[1]),
            ['mesa-diretora'],
            true
        );
    }

    /**
     * Converte URL relativa em URL absoluta.
     */
    protected function absoluteUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        if (str_starts_with($url, '/')) {
            return rtrim($this->baseUrl, '/') . $url;
        }

        return rtrim($this->baseUrl, '/') . '/' . ltrim($url, '/');
    }
}