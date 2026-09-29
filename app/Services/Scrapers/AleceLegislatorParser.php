<?php

namespace App\Services\Scrapers;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class AleceLegislatorParser
{
    protected DOMXPath $xpath;

    public function parse(
        string $html,
        string $url,
        array $context = []
    ): array
    {
        $this->xpath = $this->createXPath($html);

        $photoUrl = $this->extractPhoto();

        return [
            'external_id' => null,

            'source' => 'alece',
            'source_slug' => $this->extractSlug($url),
            'source_url' => $url,

            'name' => $this->extractName(),
            'parliamentary_name' => $this->extractParliamentaryName(),
            'party' => $this->extractParty(),
            'photo_url' => $photoUrl,
            'state' => 'CE',
            'state' => 'CE',
            'legislature' => $context['legislature'] ?? null,
            'status' => $context['status'] ?? null,
            'electoral_status' => $context['electoral_status'] ?? null,
            'phone' => $this->extractPhone(),
            'email' => $this->extractEmail(),
            'website' => $this->extractWebsite(),
            'social_media' => $this->extractSocialMedia(),

            'raw_data' => [
                'url' => $url,
            ],
        ];
    }



    /**
     * Cria o XPath a partir do HTML.
     */
    protected function createXPath(string $html): DOMXPath
    {
        libxml_use_internal_errors(true);

        $document = new DOMDocument();

        $document->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_NOERROR | LIBXML_NOWARNING
        );

        libxml_clear_errors();

        return new DOMXPath($document);
    }

    /**
     * Extrai o nome civil.
     */
    protected function extractName(): ?string
    {
        return $this->extractByLabels([
            'Nome',
            'Nome completo',
            'Nome civil',
        ]);
    }

    /**
     * Extrai o nome parlamentar.
     */
    protected function extractParliamentaryName(): ?string
    {
        return $this->extractByLabels([
            'Nome parlamentar',
            'Nome Parlamentar',
        ]);
    }

    /**
     * Extrai o partido.
     */
    protected function extractParty(): ?string
    {
        return $this->extractByLabels([
            'Partido',
            'Partido político',
            'Partido Político',
        ]);
    }

    /**
     * Extrai telefone(s) do parlamentar.
     */
    protected function extractPhone(): ?string
    {
        $nodes = $this->xpath->query(
            '//span[contains(@class, "font-weight-bold") and normalize-space(.)="Telefones"]'
        );

        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $container = $node->parentNode;

            if (!$container instanceof DOMElement) {
                continue;
            }

            $spans = $container->getElementsByTagName('span');

            foreach ($spans as $span) {
                if (!$span instanceof DOMElement) {
                    continue;
                }

                if ($span === $node) {
                    continue;
                }

                $phone = trim(
                    preg_replace(
                        '/\s+/u',
                        ' ',
                        $span->textContent
                    )
                );

                if ($phone === '') {
                    continue;
                }

                return $phone;
            }
        }

        return null;
    }

    /**
     * Extrai e-mail.
     */
    protected function extractEmail(): ?string
    {
        $nodes = $this->xpath->query(
            '//a[starts-with(translate(@href, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "mailto:")]'
        );

        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if (!$node instanceof DOMElement) {
                    continue;
                }

                $href = trim($node->getAttribute('href'));

                if (preg_match('/^mailto:(.+)$/i', $href, $matches)) {
                    return trim($matches[1]);
                }
            }
        }

        $text = $this->getPageText();

        if (preg_match(
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu',
            $text,
            $matches
        )) {
            return trim($matches[0]);
        }

        return null;
    }

    /**
     * Extrai a foto do parlamentar.
     */
    protected function extractPhoto(): ?string
    {
        $nodes = $this->xpath->query(
            '//img[@alt and @src]'
        );

        if ($nodes === false) {
            return null;
        }

        $parliamentaryName = $this->extractParliamentaryName();

        if (!$parliamentaryName) {
            return null;
        }

        $normalize = function (string $value): string {
            $value = html_entity_decode(
                $value,
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );

            $value = preg_replace('/\s+/u', ' ', $value);

            return mb_strtolower(trim($value));
        };

        $target = $normalize($parliamentaryName);

        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $alt = $normalize(
                $node->getAttribute('alt')
            );

            if ($alt !== $target) {
                continue;
            }

            $src = trim(
                $node->getAttribute('src')
            );

            if ($src === '') {
                continue;
            }

            return $this->absoluteUrl($src);
        }

        return null;
    }

    /**
     * Extrai o site pessoal do parlamentar.
     */
    protected function extractWebsite(): ?string
    {
        $nodes = $this->xpath->query(
            '//span[contains(@class, "font-weight-bold") and normalize-space(.)="Site Pessoal"]'
        );

        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            // O "Site Pessoal" está dentro de um <div>.
            $container = $node->parentNode;

            if (!$container instanceof DOMElement) {
                continue;
            }

            // Procura especificamente o segundo <span> do bloco.
            $spans = $container->getElementsByTagName('span');

            foreach ($spans as $span) {
                if (!$span instanceof DOMElement) {
                    continue;
                }

                if ($span === $node) {
                    continue;
                }

                $website = trim(
                    preg_replace(
                        '/\s+/u',
                        ' ',
                        $span->textContent
                    )
                );

                if ($website === '') {
                    continue;
                }

                return $this->normalizeWebsiteUrl($website);
            }
        }

        return null;
    }

    protected function normalizeWebsiteUrl(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        return $url;
    }

    /**
     * Extrai redes sociais.
     */
    protected function extractSocialMedia(): array
    {
        $nodes = $this->xpath->query('//a[@href]');

        if ($nodes === false) {
            return [];
        }

        $socialMedia = [];

        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $href = trim($node->getAttribute('href'));

            if ($href === '') {
                continue;
            }

            $normalized = mb_strtolower($href);

            if (str_contains($normalized, 'instagram.com')) {
                $socialMedia['instagram'] = $href;
                continue;
            }

            if (str_contains($normalized, 'facebook.com')) {
                $socialMedia['facebook'] = $href;
                continue;
            }

            if (
                str_contains($normalized, 'twitter.com') ||
                str_contains($normalized, 'x.com')
            ) {
                $socialMedia['twitter'] = $href;
                continue;
            }

            if (str_contains($normalized, 'youtube.com')) {
                $socialMedia['youtube'] = $href;
                continue;
            }

            if (str_contains($normalized, 'linkedin.com')) {
                $socialMedia['linkedin'] = $href;
            }
        }

        return $socialMedia;
    }

    /**
     * Extrai um campo através do rótulo.
     */
    protected function extractByLabels(array $labels): ?string
    {
        foreach ($labels as $label) {
            $literal = $this->xpathLiteral(
                mb_strtolower(trim($label))
            );

            $nodes = $this->xpath->query(
                '//*[normalize-space(
                    translate(
                        text(),
                        "ABCDEFGHIJKLMNOPQRSTUVWXYZÁÉÍÓÚÀÂÊÔÃÕÇ",
                        "abcdefghijklmnopqrstuvwxyzáéíóúàâêôãõç"
                    )
                )=' . $literal . ']'
            );

            if ($nodes === false) {
                continue;
            }

            foreach ($nodes as $node) {
                $value = $this->findValueAfterNode($node);

                if ($value !== null) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Procura o valor depois de um rótulo.
     */
    protected function findValueAfterNode(DOMNode $node): ?string
    {
        $parent = $node->parentNode;

        if ($parent) {
            $children = $parent->childNodes;
            $foundLabel = false;

            foreach ($children as $child) {
                if ($child === $node) {
                    $foundLabel = true;
                    continue;
                }

                if (!$foundLabel) {
                    continue;
                }

                $value = $this->cleanText($child->textContent);

                if ($value !== null) {
                    return $value;
                }
            }
        }

        $next = $node->nextSibling;

        while ($next) {
            $value = $this->cleanText($next->textContent);

            if ($value !== null) {
                return $value;
            }

            $next = $next->nextSibling;
        }

        return null;
    }

    /**
     * Retorna todo o texto da página.
     */
    protected function getPageText(): string
    {
        $body = $this->xpath->query('//body');

        if ($body === false || $body->length === 0) {
            return '';
        }

        return $body->item(0)->textContent ?? '';
    }

    /**
     * Limpa um texto extraído.
     */
    protected function cleanText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = html_entity_decode(
            $value,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $value = preg_replace('/\s+/u', ' ', $value);

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    /**
     * Extrai o slug da URL.
     */
    protected function extractSlug(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (!$path) {
            return null;
        }

        $segments = array_values(
            array_filter(
                explode('/', trim($path, '/'))
            )
        );

        return empty($segments)
            ? null
            : end($segments);
    }

    /**
     * Verifica se a URL é externa.
     */
    protected function isExternalUrl(string $url): bool
    {
        return preg_match(
            '/^https?:\/\//i',
            $url
        ) === 1;
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
            return 'https://www.al.ce.gov.br' . $url;
        }

        return 'https://www.al.ce.gov.br/' . ltrim($url, '/');
    }

    /**
     * Escapa uma string para uso no XPath.
     */
    protected function xpathLiteral(string $value): string
    {
        if (!str_contains($value, '"')) {
            return '"' . $value . '"';
        }

        if (!str_contains($value, "'")) {
            return "'" . $value . "'";
        }

        $parts = explode('"', $value);

        return 'concat("' .
            implode('", \'"\', "', $parts) .
            '")';
    }
}