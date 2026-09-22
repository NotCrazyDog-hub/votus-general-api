<?php

namespace App\Console\Commands;

use App\Models\Candidate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportCandidateProposalDocuments extends Command
{
    protected $signature = 'import:candidate-proposals
        {directory : Caminho da pasta com os PDFs de propostas}
        {--disk=supabase : Disco de destino}
        {--max-size=20480 : Tamanho máximo aceito por arquivo, em KB}';

    protected $description = 'Importa PDFs de propostas de governo e vincula pelo SQ_CANDIDATO (external_id)';

    public function handle()
    {
        $directory = rtrim($this->argument('directory'), '/');
        $disk = $this->option('disk');
        $maxSizeBytes = (int) $this->option('max-size') * 1024;

        if (!is_dir($directory)) {
            $this->error("Pasta não encontrada: {$directory}");
            return self::FAILURE;
        }

        $files = glob("{$directory}/*.{pdf,PDF}", GLOB_BRACE);

        if (empty($files)) {
            $this->warn('Nenhum arquivo .pdf encontrado na pasta.');
            return self::SUCCESS;
        }

        $this->info(count($files) . ' arquivo(s) encontrado(s). Processando...');
        $bar = $this->output->createProgressBar(count($files));

        $matched = 0;
        $notFound = [];
        $tooLarge = [];

        foreach ($files as $filePath) {
            $filename = pathinfo($filePath, PATHINFO_FILENAME);

            preg_match('/\d{8,}/', $filename, $matches);
            $externalId = $matches[0] ?? null;

            if (!$externalId) {
                $notFound[] = $filename . ' (nenhum ID numérico reconhecido no nome)';
                $bar->advance();
                continue;
            }

            $candidate = Candidate::where('external_id', $externalId)->first();

            if (!$candidate) {
                $notFound[] = "{$filename} (external_id {$externalId} não encontrado no banco)";
                $bar->advance();
                continue;
            }

            if (filesize($filePath) > $maxSizeBytes) {
                $tooLarge[] = "{$filename} (" . round(filesize($filePath) / 1024 / 1024, 1) . " MB)";
                $bar->advance();
                continue;
            }

            $storedPath = "candidates/proposals/{$externalId}.pdf";
            Storage::disk($disk)->put($storedPath, file_get_contents($filePath));

            $candidate->update(['proposal_document_path' => $storedPath]);
            $matched++;

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("{$matched} documento(s) vinculado(s) com sucesso.");

        if (!empty($tooLarge)) {
            $this->warn(count($tooLarge) . ' arquivo(s) ignorado(s) por exceder o limite de tamanho:');
            foreach ($tooLarge as $item) {
                $this->line("  - {$item}");
            }
        }

        if (!empty($notFound)) {
            $this->warn(count($notFound) . ' arquivo(s) não vinculado(s):');
            foreach ($notFound as $item) {
                $this->line("  - {$item}");
            }
        }

        return self::SUCCESS;
    }
}