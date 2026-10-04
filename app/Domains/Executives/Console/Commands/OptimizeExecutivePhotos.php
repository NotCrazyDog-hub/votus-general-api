<?php

namespace App\Domains\Executives\Console\Commands;

use App\Domains\Executives\Models\Executive;
use App\Shared\Media\OfficialPhotoOptimizer;
use Illuminate\Console\Command;

/**
 * Executive não tem um comando de sync (os 4 registros — presidente,
 * vice-presidente, governador, vice-governadora — são cadastrados
 * manualmente), então esse é um comando à parte em vez de um passo dentro
 * de um sync que não existe.
 */
class OptimizeExecutivePhotos extends Command
{
    protected $signature = 'optimize:executive-photos {--force : Reprocessa mesmo quem já está no bucket}';
    protected $description = 'Baixa, redimensiona e guarda no Supabase as fotos de presidente/vice/governador/vice-governadora';

    public function handle(OfficialPhotoOptimizer $photoOptimizer)
    {
        $publicUrlPrefix = rtrim((string) config('filesystems.disks.supabase.public_url'), '/');
        $force = (bool) $this->option('force');

        $executives = Executive::whereNotNull('photo_url')->get();
        $this->info($executives->count() . ' executivo(s) com foto encontrados.');

        $processed = 0;
        $skipped = 0;
        $failed = [];

        foreach ($executives as $executive) {
            // Já otimizada nesta rodada anterior — pula, a menos que --force.
            if (! $force && str_starts_with($executive->photo_url, $publicUrlPrefix)) {
                $skipped++;
                continue;
            }

            $newUrl = $photoOptimizer->resizeAndStore(
                $executive->photo_url,
                "executives/photos/{$executive->id}.jpg"
            );

            if ($newUrl === null) {
                $failed[] = $executive->display_name ?? $executive->office;
                continue;
            }

            $executive->update(['photo_url' => $newUrl]);
            $processed++;
        }

        $this->info("{$processed} foto(s) otimizada(s), {$skipped} já estavam otimizadas.");

        if (! empty($failed)) {
            $this->warn(count($failed) . ' falharam: ' . implode(', ', $failed));
        }

        return self::SUCCESS;
    }
}
