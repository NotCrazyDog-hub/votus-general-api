<?php

namespace App\Domains\Radar\Services;

use RuntimeException;

/**
 * Conta, por UF, os temas oficiais das proposições de deputados a partir dos
 * arquivos anuais em lote da Câmara (proposicoesTemas-{ano}.csv e
 * proposicoesAutores-{ano}.csv). Não acessa rede nem banco.
 *
 * Regra de contagem (documentada também na resposta da API):
 *  - Entram só PL e PEC (config radar.bill_types) que já têm ao menos um tema
 *    oficial no arquivo de temas. Proposição ainda sem tema não aparece nesse
 *    arquivo, então não é contada (e o tipo dela nem é conhecido sem baixar
 *    o arquivo de proposições, de ~90 MB).
 *  - Autor = linha do arquivo de autores com codTipoAutor de deputado e
 *    proponente = 1. Linhas com proponente = 0 são assinaturas de APOIO (ex.:
 *    as 171 exigidas para PEC) e não contam como autoria — conferido contra
 *    /api/v2/proposicoes/{id}/autores.
 *  - UF = siglaUFAutor da linha do deputado. Linha de deputado sem UF (ex-
 *    deputados hoje senadores, em proposições vindas do Senado) não tem UF
 *    verificável: é descartada e contada em checks.author_rows_without_uf.
 *  - Cada proposição conta UMA vez por UF, mesmo com vários coautores da
 *    mesma UF. Com coautores de UFs diferentes, conta uma vez em cada UF.
 *  - Proposição com vários temas conta uma vez em cada tema; por isso a soma
 *    dos temas de uma UF passa do total de proposições da UF.
 *
 * Qualquer problema de estrutura (cabeçalho diferente, linha com número
 * errado de colunas, ano diferente do pedido, id não numérico) lança
 * exceção: arquivo suspeito nunca vira resultado.
 */
class CamaraTopicAggregator
{
    public const THEMES_HEADER = ['uriProposicao', 'siglaTipo', 'numero', 'ano', 'codTema', 'tema', 'relevancia'];

    public const AUTHORS_HEADER = [
        'idProposicao', 'uriProposicao', 'idDeputadoAutor', 'uriAutor', 'codTipoAutor', 'tipoAutor',
        'nomeAutor', 'siglaPartidoAutor', 'uriPartidoAutor', 'siglaUFAutor', 'ordemAssinatura', 'proponente',
    ];

    /**
     * @param  string[]  $billTypes
     */
    public function aggregate(string $themesPath, string $authorsPath, int $year, array $billTypes, string $deputyAuthorType): array
    {
        [$billThemes, $themeNames, $themeChecks] = $this->readThemes($themesPath, $year, array_flip($billTypes));

        $checks = $themeChecks + [
            'author_rows' => 0,
            'author_rows_other_types' => 0,
            'author_rows_supporters' => 0,
            'author_rows_without_uf' => 0,
            'author_rows_used' => 0,
        ];

        // $byUf[uf][idProposicao] = nº de deputados proponentes daquela UF
        $byUf = [];
        $deputiesByUf = [];
        $nationalBills = [];

        foreach ($this->rows($authorsPath, self::AUTHORS_HEADER) as $line => $row) {
            $checks['author_rows']++;

            // Lê só as colunas necessárias do CSV; os campos vazios mantêm a
            // posição original de cada coluna na linha.
            [$billId, , $deputyId, , $authorType, , , , , $uf, , $proponent] = $row;

            if (! ctype_digit($billId)) {
                throw new RuntimeException("Autores: idProposicao inválido na linha {$line}");
            }

            // Só entram proposições que passaram pelo filtro de tipo e têm
            // tema oficial, já indexadas por readThemes.
            if (! isset($billThemes[$billId])) {
                continue; // não é PL/PEC com tema deste ano
            }

            if ($authorType !== $deputyAuthorType) {
                $checks['author_rows_other_types']++;
                continue;
            }

            if ($proponent !== '1') {
                $checks['author_rows_supporters']++;
                continue;
            }

            if (! RadarUfs::isValid($uf) || $uf !== strtoupper($uf)) {
                $checks['author_rows_without_uf']++;
                continue;
            }

            $checks['author_rows_used']++;
            // Guarda autores por proposição/UF: na saída, a proposição é
            // contada uma vez, mas ainda registramos se teve coautoria local.
            $byUf[$uf][$billId] = ($byUf[$uf][$billId] ?? 0) + 1;
            $nationalBills[$billId] = true;

            if ($deputyId !== '') {
                $deputiesByUf[$uf][$deputyId] = true;
            }
        }

        $states = [];
        foreach ($byUf as $uf => $bills) {
            $states[$uf] = $this->summarizeState($bills, $billThemes, $themeNames, count($deputiesByUf[$uf] ?? []));
        }
        ksort($states);

        return [
            'year' => $year,
            'totals' => [
                'themed_bills_in_file' => count($billThemes),
                'bills_with_deputy_author' => count($nationalBills),
                'states_with_data' => count($states),
            ],
            'checks' => $checks,
            'states' => $states,
        ];
    }

    /**
     * @param  array<string, int>  $bills  idProposicao => nº de proponentes da UF
     */
    private function summarizeState(array $bills, array $billThemes, array $themeNames, int $deputies): array
    {
        $counts = [];
        $multiTheme = 0;
        $multiAuthor = 0;

        foreach ($bills as $billId => $authors) {
            $themes = $billThemes[$billId];

            if (count($themes) > 1) {
                $multiTheme++;
            }

            if ($authors > 1) {
                $multiAuthor++;
            }

            // Uma proposição com vários temas incrementa cada tema uma vez;
            // por isso as contagens temáticas podem superar o total de projetos.
            foreach ($themes as $code => $_) {
                $counts[$code] = ($counts[$code] ?? 0) + 1;
            }
        }

        $topics = [];
        foreach ($counts as $code => $count) {
            $topics[] = ['code' => (string) $code, 'name' => $themeNames[$code], 'count' => $count];
        }

        usort($topics, fn ($a, $b) => [$b['count'], $a['name']] <=> [$a['count'], $b['name']]);

        return [
            'propositions' => count($bills),
            'deputies' => $deputies,
            'multi_theme_propositions' => $multiTheme,
            'multi_author_same_uf_propositions' => $multiAuthor,
            'topics' => $topics,
        ];
    }

    /**
     * @return array{0: array<string, array<string, true>>, 1: array<string, string>, 2: array<string, int>}
     */
    private function readThemes(string $path, int $year, array $billTypes): array
    {
        $billThemes = [];
        $themeNames = [];
        $checks = ['theme_rows' => 0, 'theme_rows_used' => 0, 'theme_rows_duplicated' => 0];

        foreach ($this->rows($path, self::THEMES_HEADER) as $line => $row) {
            $checks['theme_rows']++;

            [$uri, $type, , $rowYear, $code, $name] = $row;

            if ((int) $rowYear !== $year) {
                throw new RuntimeException("Temas: linha {$line} é do ano {$rowYear}, esperado {$year}");
            }

            if (! isset($billTypes[$type])) {
                continue;
            }

            $billId = substr($uri, strrpos($uri, '/') + 1);

            if (! ctype_digit($billId) || ! ctype_digit($code) || trim($name) === '') {
                throw new RuntimeException("Temas: linha {$line} com id, código ou nome de tema inválido");
            }

            if (isset($billThemes[$billId][$code])) {
                $checks['theme_rows_duplicated']++;
                continue;
            }

            $checks['theme_rows_used']++;
            $billThemes[$billId][$code] = true;
            $themeNames[$code] = trim($name);
        }

        return [$billThemes, $themeNames, $checks];
    }

    /**
     * Lê um CSV oficial da Câmara (';' e aspas, UTF-8 com BOM) linha a linha,
     * conferindo o cabeçalho e o número de colunas de cada linha.
     */
    private function rows(string $path, array $expectedHeader): \Generator
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Não foi possível abrir {$path}");
        }

        try {
            // O BOM vem antes da primeira aspa e confundiria o fgetcsv.
            if (fread($handle, 3) !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $header = fgetcsv($handle, null, ';', '"', '');

            if ($header === false) {
                throw new RuntimeException('Arquivo vazio: ' . basename($path));
            }

            if ($header !== $expectedHeader) {
                throw new RuntimeException('Cabeçalho inesperado em ' . basename($path) . ': ' . implode(';', $header));
            }

            $columns = count($expectedHeader);
            $line = 1;

            while (($row = fgetcsv($handle, null, ';', '"', '')) !== false) {
                $line++;

                if ($row === [null]) {
                    continue; // linha em branco (ex.: final do arquivo)
                }

                if (count($row) !== $columns) {
                    throw new RuntimeException(basename($path) . ": linha {$line} com " . count($row) . " colunas, esperado {$columns}");
                }

                yield $line => $row;
            }
        } finally {
            fclose($handle);
        }
    }
}
