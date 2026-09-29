<?php

namespace App\Services;

use Generator;

class TseCandidatesCsvService
{
    /**
     * Lê candidatos e cruza com o CSV de informações complementares
     * através do SQ_CANDIDATO.
     *
     * @param string $filePath
     * @param string $complementaryFilePath
     * @param string $uf
     * @param array<string> $offices
     * @return Generator<array>
     */
    public function readCandidates(
        string $filePath,
        string $complementaryFilePath,
        string $uf,
        array $offices
    ): Generator {
        $complementary = $this->loadComplementaryData($complementaryFilePath);

        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new \RuntimeException(
                "Não foi possível abrir o arquivo: {$filePath}"
            );
        }

        $offices = array_map(
            fn ($o) => mb_strtoupper(trim($o)),
            $offices
        );

        $header = null;

        try {
            while (($row = fgetcsv($handle, 0, ';', '"')) !== false) {
                $row = array_map(
                    fn ($value) => $value === null
                        ? null
                        : $this->nullIfPlaceholder(
                            mb_convert_encoding(
                                $value,
                                'UTF-8',
                                'ISO-8859-1'
                            )
                        ),
                    $row
                );

                if ($header === null) {
                    $header = $row;
                    continue;
                }

                if (count($header) !== count($row)) {
                    continue;
                }

                $data = array_combine($header, $row);

                if (
                    mb_strtoupper((string) ($data['SG_UF'] ?? ''))
                    !== mb_strtoupper($uf)
                ) {
                    continue;
                }

                if (
                    !in_array(
                        mb_strtoupper((string) ($data['DS_CARGO'] ?? '')),
                        $offices,
                        true
                    )
                ) {
                    continue;
                }

                $externalId = (string) ($data['SQ_CANDIDATO'] ?? '');

                if (isset($complementary[$externalId])) {
                    $data['CD_SITUACAO_JULGAMENTO'] =
                        $complementary[$externalId]['CD_SITUACAO_JULGAMENTO']
                        ?? null;

                    $data['DS_SITUACAO_JULGAMENTO'] =
                        $complementary[$externalId]['DS_SITUACAO_JULGAMENTO']
                        ?? null;
                } else {
                    $data['CD_SITUACAO_JULGAMENTO'] = null;
                    $data['DS_SITUACAO_JULGAMENTO'] = null;
                }

                yield $data;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Carrega somente as informações de julgamento
     * necessárias do CSV complementar.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function loadComplementaryData(string $filePath): array
    {
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new \RuntimeException(
                "Não foi possível abrir o arquivo complementar: {$filePath}"
            );
        }

        $data = [];
        $header = null;

        try {
            while (($row = fgetcsv($handle, 0, ';', '"')) !== false) {
                $row = array_map(
                    fn ($value) => $value === null
                        ? null
                        : $this->nullIfPlaceholder(
                            mb_convert_encoding(
                                $value,
                                'UTF-8',
                                'ISO-8859-1'
                            )
                        ),
                    $row
                );

                if ($header === null) {
                    $header = $row;
                    continue;
                }

                if (count($header) !== count($row)) {
                    continue;
                }

                $item = array_combine($header, $row);

                $externalId = (string) (
                    $item['SQ_CANDIDATO'] ?? ''
                );

                if ($externalId === '') {
                    continue;
                }

                $data[$externalId] = [
                    'CD_SITUACAO_JULGAMENTO' =>
                        $item['CD_SITUACAO_JULGAMENTO'] ?? null,

                    'DS_SITUACAO_JULGAMENTO' =>
                        $item['DS_SITUACAO_JULGAMENTO'] ?? null,
                ];
            }
        } finally {
            fclose($handle);
        }

        return $data;
    }

    /**
     * O TSE usa "#NULO", "#NULO#" e "#NE"
     * como placeholder de campo vazio/não se aplica.
     */
    protected function nullIfPlaceholder(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = mb_strtoupper(trim($value));

        return in_array(
            $normalized,
            ['#NULO', '#NULO#', '#NE', '#NE#'],
            true
        )
            ? null
            : $value;
    }
}