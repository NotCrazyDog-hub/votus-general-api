<?php

namespace App\Services;

use Generator;

class TseCandidatesCsvService
{
    /**
     * @param string $filePath
     * @param string $uf
     * @param array<string> $offices
     * @return Generator<array>
     */
    public function readCandidates(string $filePath, string $uf, array $offices): Generator
    {
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new \RuntimeException("Não foi possível abrir o arquivo: {$filePath}");
        }

        $offices = array_map(fn ($o) => mb_strtoupper(trim($o)), $offices);
        $header = null;

        try {
            while (($row = fgetcsv($handle, 0, ';', '"')) !== false) {
                $row = array_map(
                    fn ($value) => $value === null ? null : $this->nullIfPlaceholder(
                        mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1')
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

                if (mb_strtoupper((string) ($data['SG_UF'] ?? '')) !== mb_strtoupper($uf)) {
                    continue;
                }

                if (!in_array(mb_strtoupper((string) ($data['DS_CARGO'] ?? '')), $offices, true)) {
                    continue;
                }

                yield $data;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * O TSE usa "#NULO", "#NULO#" e "#NE" como placeholder de campo vazio/não se aplica.
     */
    protected function nullIfPlaceholder(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = mb_strtoupper(trim($value));

        return in_array($normalized, ['#NULO', '#NULO#', '#NE', '#NE#'], true)
            ? null
            : $value;
    }
}