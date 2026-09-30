<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ExecutiveActionImportService
{
    public function read(string $path): array
    {
        if (!file_exists($path)) {
            throw new RuntimeException(
                "Arquivo CSV não encontrado: {$path}"
            );
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException(
                "Não foi possível abrir o arquivo: {$path}"
            );
        }

        $headers = fgetcsv($handle);

        if (!$headers) {
            fclose($handle);

            throw new RuntimeException(
                'O CSV está vazio ou não possui cabeçalho.'
            );
        }

        $headers = array_map(
            fn ($header) => $this->normalizeHeader($header),
            $headers
        );

        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($this->isEmptyRow($data)) {
                continue;
            }

            $data = array_pad($data, count($headers), null);

            $row = array_combine(
                $headers,
                array_slice($data, 0, count($headers))
            );

            $rows[] = $this->normalizeRow($row);
        }

        fclose($handle);

        return $rows;
    }

    private function normalizeHeader(string $header): string
    {
        $header = trim($header);

        // Remove BOM do primeiro campo
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);

        return $header;
    }

    private function normalizeRow(array $row): array
    {
        return [
            'executive_id' => $this->nullableInteger(
                $row['executive_id'] ?? null
            ),

            'title' => $this->nullableString(
                $row['title'] ?? null
            ),

            'summary' => $this->nullableString(
                $row['summary'] ?? null
            ),

            'action_type' => $this->nullableString(
                $row['action_type'] ?? null
            ),

            'occurred_at' => $this->nullableString(
                $row['occurred_at'] ?? null
            ),

            'published_at' => $this->nullableString(
                $row['published_at'] ?? null
            ),

            'location' => $this->nullableString(
                $row['location'] ?? null
            ),

            'source_name' => $this->nullableString(
                $row['source_name'] ?? null
            ),

            'source_type' => $this->nullableString(
                $row['source_type'] ?? null
            ),

            'source_url' => $this->nullableString(
                $row['source_url'] ?? null
            ),

            'entities' => $this->decodeJson(
                $row['entities'] ?? null
            ),

            'raw_data' => $this->decodeJson(
                $row['raw_data'] ?? null
            ),

            'relevance_score' => $this->nullableFloat(
                $row['relevance_score'] ?? null
            ),

            'analysis_status' => $this->nullableString(
                $row['analysis_status'] ?? null
            ) ?? 'pending',

            'analyzed_at' => $this->nullableString(
                $row['analyzed_at'] ?? null
            ),
        ];
    }

    private function nullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function nullableInteger(?string $value): ?int
    {
        $value = $this->nullableString($value);

        return $value === null ? null : (int) $value;
    }

    private function nullableFloat(?string $value): ?float
    {
        $value = $this->nullableString($value);

        if ($value === null) {
            return null;
        }

        return (float) str_replace(',', '.', $value);
    }

    private function decodeJson(?string $value): ?array
    {
        $value = $this->nullableString($value);

        if ($value === null) {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE
            ? $decoded
            : null;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (
                $value !== null &&
                trim((string) $value) !== ''
            ) {
                return false;
            }
        }

        return true;
    }
}