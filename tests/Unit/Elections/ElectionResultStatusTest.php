<?php

namespace Tests\Unit\Elections;

use App\Domains\Elections\Support\ElectionResultStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ElectionResultStatusTest extends TestCase
{
    /**
     * Textos oficiais do TSE vistos em candidacy_histories (eleição 2026).
     */
    public static function officialTexts(): array
    {
        return [
            ['Eleito', 'elected'],
            ['Eleito por QP', 'elected'],
            ['Eleito por média', 'elected'],
            ['Não eleito', 'not_elected'],
            ['Suplente', 'not_elected'],
            ['2º turno', 'runoff'],
            ['Renúncia', 'unverified'],
        ];
    }

    #[DataProvider('officialTexts')]
    public function test_it_classifies_the_official_tse_texts(string $text, string $status): void
    {
        $this->assertSame($status, ElectionResultStatus::classify($text)['status']);
    }

    public function test_the_latest_round_with_a_result_wins(): void
    {
        $histories = collect([
            (object) ['election_year' => 2026, 'round' => 1, 'result_status' => '2º turno'],
            (object) ['election_year' => 2026, 'round' => 2, 'result_status' => 'Eleito'],
        ]);

        $result = ElectionResultStatus::fromHistories($histories, 2026);

        $this->assertSame('elected', $result['status']);
        $this->assertSame(2, $result['round']);
        $this->assertSame('Eleito', $result['official_text']);
    }

    public function test_an_empty_second_round_keeps_the_runoff_status(): void
    {
        $histories = collect([
            (object) ['election_year' => 2026, 'round' => 1, 'result_status' => '2º turno'],
            (object) ['election_year' => 2026, 'round' => 2, 'result_status' => null],
        ]);

        $this->assertSame('runoff', ElectionResultStatus::fromHistories($histories, 2026)['status']);
    }

    public function test_a_candidacy_without_result_is_awaiting_and_no_record_is_unverified(): void
    {
        $empty = collect([(object) ['election_year' => 2026, 'round' => 1, 'result_status' => '']]);
        $otherYear = collect([(object) ['election_year' => 2022, 'round' => 1, 'result_status' => 'Eleito']]);

        $this->assertSame('awaiting', ElectionResultStatus::fromHistories($empty, 2026)['status']);
        // Resultado de outra eleição não vale para a de 2026.
        $this->assertSame('unverified', ElectionResultStatus::fromHistories($otherYear, 2026)['status']);
        $this->assertSame('unverified', ElectionResultStatus::fromHistories(collect(), 2026)['status']);
    }
}
