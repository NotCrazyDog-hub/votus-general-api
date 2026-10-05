<?php

namespace Tests\Feature\Tse;

use App\Enums\CandidateOffice;
use App\Models\Candidate;
use App\Services\Tse\CandidateSyncService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class CandidateFilesTest extends CandidateSyncTestCase
{
    use FakeTseApi;

    public function test_it_stores_the_photo_path_and_never_the_tse_url(): void
    {
        $this->fakeCandidateApi();

        $summary = $this->sync(withFiles: true);

        $this->assertSame(1, $summary['photos']);

        $candidate = Candidate::firstOrFail();
        $path = $candidate->photo_path;

        // photo_path é caminho de arquivo; a URL do TSE só serve para baixar.
        $this->assertSame('candidates/photos/60002543969.jpg', $path);
        $this->assertStringNotContainsString('http', $path);
        $this->assertNotNull($candidate->photo_url);

        Storage::disk('supabase')->assertExists('candidates/photos/60002543969.jpg');
    }

    public function test_it_prefers_the_publishable_photo_url(): void
    {
        $this->fakeCandidateApi();

        $this->sync(withFiles: true);

        // O stub responde só em */arquivo/img/*, que é o formato das duas
        // variantes; o serviço precisa ter escolhido fotoUrlPublicavel.
        Http::assertSent(
            fn ($r) => str_contains($r->url(), '/arquivo/img/20322002026/60002543969/CE'),
        );
    }

    public function test_it_downloads_the_government_plan_with_file_type_5(): void
    {
        $this->fakeCandidateApi($this->candidateDetail([
            'arquivos' => [
                // codTipo 4 (foto oficial) NÃO deve ser escolhido.
                ['idArquivo' => 11111, 'codTipo' => '4', 'nomeArquivo' => 'foto'],
                // codTipo 5 é o plano de governo.
                ['idArquivo' => 99999, 'codTipo' => '5', 'nomeArquivo' => 'plano'],
                ['idArquivo' => 22222, 'codTipo' => '1', 'nomeArquivo' => 'outro'],
            ],
        ]));

        $summary = $this->sync(withFiles: true);

        $this->assertSame(1, $summary['documents']);

        $candidate = Candidate::firstOrFail();

        $this->assertSame('candidates/proposals/60002543969.pdf', $candidate->proposal_document_path);

        Storage::disk('supabase')->assertExists('candidates/proposals/60002543969.pdf');

        Http::assertSent(
            fn ($r) => str_contains($r->url(), '/doc/99999'),
        );
        Http::assertSent(
            fn ($r) => ! str_contains($r->url(), '/doc/11111'),
        );
    }

    public function test_it_skips_the_document_when_there_is_no_file_type_5(): void
    {
        $this->fakeCandidateApi($this->candidateDetail([
            'arquivos' => [
                ['idArquivo' => 11111, 'codTipo' => '4'],
            ],
        ]));

        $summary = $this->sync(withFiles: true);

        $this->assertSame(0, $summary['documents']);
        $this->assertNull(Candidate::firstOrFail()->proposal_document_path);
    }

    public function test_a_failed_download_does_not_lose_the_candidate(): void
    {
        // Foto e documento respondem 404: o download devolve null e o
        // candidato já persistido deve ficar de pé.
        $this->fakeCandidateApi(
            $this->candidateDetail([
                'arquivos' => [['idArquivo' => 99999, 'codTipo' => '5']],
            ]),
            fileStatus: 404,
        );

        $summary = $this->sync(withFiles: true);

        $this->assertSame(1, $summary['persisted']);
        $this->assertSame([], $summary['failed']);
        $this->assertSame(0, $summary['photos']);
        $this->assertSame(0, $summary['documents']);

        $candidate = Candidate::firstOrFail();
        $this->assertNull($candidate->photo_path);
        $this->assertNull($candidate->proposal_document_path);
    }

    public function test_without_files_no_download_is_attempted(): void
    {
        $this->fakeCandidateApi();

        $summary = $this->sync(withFiles: false);

        $this->assertSame(1, $summary['persisted']);
        $this->assertSame(0, $summary['photos']);
        $this->assertSame(0, $summary['documents']);

        $candidate = Candidate::firstOrFail();
        $this->assertNull($candidate->photo_path);
        $this->assertNull($candidate->proposal_document_path);

        Http::assertSentCount(2); // listar + buscar
    }

    private function sync(bool $withFiles): array
    {
        return app(CandidateSyncService::class)->syncOffice(
            year: 2026,
            uf: 'CE',
            electionId: $this->electionId(),
            office: CandidateOffice::StateDeputy,
            disk: 'supabase',
            withFiles: $withFiles,
        );
    }
}
