<?php

namespace App\Domains\Legislatures\Services\Alece\Scrapers;

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
     * Descobre as URLs dos deputados no site da ALECE
     * juntamente com o contexto da composição atual.
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
        */
        $legislature = 31;

        /*
        * A página possui duas seções:
        *
        * 1. Em Exercício e Licenciados
        * 2. Suplentes em Exercício
        *
        * A primeira usa <h1>.
        * A segunda usa <h2>.
        */
        $headings = $xpath->query(
            '//h1[contains(@class, "main_page--title")]
            | //h2[contains(@class, "main_page--title")]'
        );

        if ($headings === false) {
            return [];
        }

        foreach ($headings as $heading) {
            if (!$heading instanceof \DOMElement) {
                continue;
            }

            /*
            * textContent também captura o texto
            * que está dentro do <span>.
            *
            * Exemplo:
            *
            * Em Exercício e <span>Licenciados</span>
            *
            * vira:
            *
            * Em Exercício e Licenciados
            */
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
                $electoralStatus = 'alternate';
            } elseif (
                mb_stripos(
                    $title,
                    'Em Exercício e Licenciados'
                ) !== false
            ) {
                $electoralStatus = 'sitting';
            } else {
                continue;
            }

            /*
            * O título e os cards estão dentro
            * do mesmo <div class="row ...">.
            */
            $container = $heading->parentNode;

            if (!$container instanceof \DOMElement) {
                continue;
            }

            /*
            * Busca somente os cards pertencentes
            * à seção atual.
            */
            $cards = $xpath->query(
                './/div[contains(
                    concat(" ", normalize-space(@class), " "),
                    " deputado_card "
                )]',
                $container
            );

            if ($cards === false) {
                continue;
            }

            foreach ($cards as $card) {
                if (!$card instanceof \DOMElement) {
                    continue;
                }

                /*
                * Localiza o link do parlamentar.
                */
                $link = $xpath->query(
                    './/p[contains(@class, "deputado_card--nome")]//a[@href]',
                    $card
                );

                if ($link === false || $link->length === 0) {
                    continue;
                }

                /*
                * item() retorna DOMNode|null; getAttribute()
                * só existe em DOMElement.
                */
                $anchor = $link->item(0);

                if (!$anchor instanceof \DOMElement) {
                    continue;
                }

                $href = trim($anchor->getAttribute('href'));

                $url = $this->absoluteUrl($href);

                if (!$url || !$this->isLegislatorUrl($url)) {
                    continue;
                }

                /*
                * Verifica se o parlamentar está licenciado.
                *
                * Exemplo:
                *
                * class="deputado_card licenciado"
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

                    'state' => 'CE',
                ];
            }
        }

        /*
        * Remove possíveis duplicados pelo URL.
        *
        * Isso é importante porque um parlamentar pode
        * aparecer em mais de uma seção.
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
    public function scrape(
        string $url,
        array $context = []
    ): array {
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

        if (!preg_match(
            '#^deputados/([^/]+)$#i',
            $path,
            $matches
        )) {
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

        if (preg_match(
            '#^https?://#i',
            $url
        )) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        if (str_starts_with($url, '/')) {
            return rtrim($this->baseUrl, '/') . $url;
        }

        return rtrim($this->baseUrl, '/') . '/'
            . ltrim($url, '/');
    }
}