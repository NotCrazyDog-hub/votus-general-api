<?php

namespace App\Domains\Elections\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Situação eleitoral do candidato, derivada SÓ do resultado oficial do TSE já
 * importado em candidacy_histories.result_status (comando tse:import-results).
 * Nada é estimado: sem resultado importado, a situação fica "não verificada".
 *
 * Textos oficiais vistos no banco (eleição 2026): "Eleito", "Eleito por QP",
 * "Eleito por média", "Não eleito", "Suplente", "2º turno".
 *
 * Estados:
 *  - elected       Eleito (inclui por QP e por média)
 *  - not_elected   Não eleito (inclui suplente: não ganhou a vaga)
 *  - runoff        Segundo turno (classificado; o 2º turno ainda não tem resultado)
 *  - awaiting      Aguardando resultado (há registro da candidatura no ano, sem resultado)
 *  - unverified    Situação não verificada (sem registro, ou texto não reconhecido)
 */
class ElectionResultStatus
{
    /**
     * @param  Collection  $histories  candidacy_histories do candidato (qualquer ano)
     */
    public static function fromHistories(Collection $histories, ?int $electionYear): array
    {
        $ofYear = $histories->where('election_year', $electionYear);

        // Turno mais recente com resultado. Se o 2º turno ainda está vazio,
        // vale o resultado do 1º ("2º turno") — o candidato segue na disputa.
        $withResult = $ofYear
            ->filter(fn ($h) => trim((string) $h->result_status) !== '')
            ->sortByDesc('round')
            ->first();

        if ($withResult) {
            return self::classify($withResult->result_status) + [
                'official_text' => $withResult->result_status,
                'election_year' => $electionYear,
                'round' => $withResult->round,
                'source' => 'TSE',
            ];
        }

        if ($ofYear->isNotEmpty()) {
            return [
                'status' => 'awaiting',
                'label' => 'Aguardando resultado',
                'detail' => 'A candidatura consta no TSE, mas o resultado oficial ainda não foi publicado ou importado.',
                'official_text' => null,
                'election_year' => $electionYear,
                'round' => null,
                'source' => 'TSE',
            ];
        }

        return [
            'status' => 'unverified',
            'label' => 'Situação não verificada',
            'detail' => 'O Votus ainda não tem o resultado oficial do TSE para esta candidatura.',
            'official_text' => null,
            'election_year' => $electionYear,
            'round' => null,
            'source' => null,
        ];
    }

    /**
     * @return array{status: string, label: string, detail: string}
     */
    public static function classify(string $officialText): array
    {
        $text = Str::of($officialText)->ascii()->lower()->squish()->toString();

        if (str_contains($text, 'turno') && preg_match('/\b2/', $text)) {
            return [
                'status' => 'runoff',
                'label' => 'Segundo turno',
                'detail' => 'Classificado para o 2º turno. O resultado final depende da votação do 2º turno.',
            ];
        }

        if (str_starts_with($text, 'nao eleito')) {
            return ['status' => 'not_elected', 'label' => 'Não eleito', 'detail' => 'Resultado oficial do TSE.'];
        }

        if ($text === 'suplente') {
            return [
                'status' => 'not_elected',
                'label' => 'Não eleito',
                'detail' => 'Ficou como suplente: não ganhou a vaga, mas pode assumi-la se um eleito do partido ou da federação deixar o cargo.',
            ];
        }

        if (str_starts_with($text, 'eleito')) {
            $detail = match (true) {
                str_contains($text, 'qp') => 'Eleito pelo quociente partidário (QP), na distribuição principal das vagas.',
                str_contains($text, 'media') => 'Eleito por média, na distribuição das vagas que sobraram.',
                default => 'Resultado oficial do TSE.',
            };

            return ['status' => 'elected', 'label' => 'Eleito', 'detail' => $detail];
        }

        return [
            'status' => 'unverified',
            'label' => 'Situação não verificada',
            'detail' => "Situação informada pelo TSE não reconhecida pelo Votus: \"{$officialText}\".",
        ];
    }
}
