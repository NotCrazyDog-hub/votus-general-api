<?php

namespace App\Domains\Elections\Services\Tse;

use Generator;
use Carbon\Carbon;

class TseCandidateExpensesCsvService
{
    public function readExpenses(string $filePath): Generator
    {
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new \RuntimeException("Não foi possível abrir o arquivo: {$filePath}");
        }

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

                yield array_combine($header, $row);
            }
        } finally {
            fclose($handle);
        }
    }

    public function parseBrazilianDecimal(?string $value): float
    {
        if ($value === null) {
            return 0.0;
        }

        $normalized = str_replace('.', '', $value); // remove separador de milhar
        $normalized = str_replace(',', '.', $normalized); // vírgula decimal -> ponto

        return (float) $normalized;
    }

    public function parseBrazilianDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

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