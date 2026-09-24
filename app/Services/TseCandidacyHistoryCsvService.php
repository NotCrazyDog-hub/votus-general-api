<?php

namespace App\Services;

use Generator;

class TseCandidacyHistoryCsvService
{
    public function readHistory(
        string $filePath,
        string $currentUf,
        int $currentYear
    ): Generator {
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new \RuntimeException(
                "Não foi possível abrir o arquivo: {$filePath}"
            );
        }

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
                    mb_strtoupper(
                        trim((string) ($data['SG_UF_ATUAL'] ?? ''))
                    ) !== mb_strtoupper(trim($currentUf))
                ) {
                    continue;
                }

                if (
                    (int) ($data['ANO_ELEICAO_ATUAL'] ?? 0)
                    !== $currentYear
                ) {
                    continue;
                }

                if (empty($data['SQ_CANDIDATO'])) {
                    continue;
                }

                if (
                    (int) ($data['ANO_ELEICAO'] ?? 0)
                    === (int) ($data['ANO_ELEICAO_ATUAL'] ?? 0)
                ) {
                    continue;
                }

                if (empty(trim((string) ($data['DS_SIT_TOT_TURNO'] ?? '')))) {
                    continue;
                }

                if (
                    mb_strtoupper(trim((string) ($data['DS_SIT_TOT_TURNO'] ?? '')))
                    !== 'ELEITO'
                ) {
                    continue;
                }

                yield $data;
            }
        } finally {
            fclose($handle);
        }
    }

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