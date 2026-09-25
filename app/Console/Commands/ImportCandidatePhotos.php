<?php

namespace App\Console\Commands;

use App\Models\Candidate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportCandidatePhotos extends Command
{
    //para chamar o command para a pasta:
    //'php artisan import:candidate-photos storage/app/tse/foto_cand2026_CE_div'
    
    protected $signature = 'import:candidate-photos
        {directory : Caminho da pasta com os JPGs (ex: foto_cand2026_CE_div)}
        {--disk=supabase : Disco de destino}';

    protected $description = 'Importa fotos de candidatos a partir de uma pasta local e vincula pelo SQ_CANDIDATO (external_id)';

    public function handle()
    {
        $directory = rtrim($this->argument('directory'), '/');
        $disk = $this->option('disk');

        if (!is_dir($directory)) {
            $this->error("Pasta não encontrada: {$directory}");
            return self::FAILURE;
        }

        $files = glob("{$directory}/*.{jpg,jpeg,JPG,JPEG}", GLOB_BRACE);

        if (empty($files)) {
            $this->warn('Nenhum arquivo .jpg encontrado na pasta.');
            return self::SUCCESS;
        }

        $this->info(count($files) . ' arquivo(s) encontrado(s). Processando...');
        $bar = $this->output->createProgressBar(count($files));

        $matched = 0;
        $notFound = [];

        foreach ($files as $filePath) {
            $filename = pathinfo($filePath, PATHINFO_FILENAME);

            // Extrai apenas os dígitos do nome do arquivo — cobre tanto
            // "60002542208.jpg" quanto variações com prefixo/sufixo.
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

            $storedPath = "candidates/photos/{$externalId}.jpg";
            Storage::disk($disk)->put($storedPath, file_get_contents($filePath));

            $candidate->update(['photo_path' => $storedPath]);
            $matched++;

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("{$matched} foto(s) vinculada(s) com sucesso.");

        if (!empty($notFound)) {
            $this->warn(count($notFound) . ' arquivo(s) não vinculado(s):');
            foreach ($notFound as $item) {
                $this->line("  - {$item}");
            }
        }

        return self::SUCCESS;
    }
}