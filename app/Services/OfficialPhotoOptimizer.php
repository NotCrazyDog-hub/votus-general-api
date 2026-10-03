<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Baixa a foto oficial de um parlamentar/executivo (Câmara, Senado, ALECE,
 * site do Governo do CE — fontes que não controlamos e cujas fotos variam
 * de ~9KB a ~4MB), redimensiona pra um tamanho pequeno e guarda no mesmo
 * bucket Supabase usado pelas fotos de candidato. Isso evita servir a foto
 * original gigante direto pro navegador do visitante.
 */
class OfficialPhotoOptimizer
{
    /**
     * Retorna a URL pública da foto já redimensionada, ou null se não foi
     * possível processar (fonte fora do ar, imagem inválida, etc.) — quem
     * chamar deve usar a URL original como fallback nesse caso, nunca deixar
     * o sync falhar por causa disso.
     */
    public function resizeAndStore(string $sourceUrl, string $storagePath, string $disk = 'supabase'): ?string
    {
        try {
            $response = Http::timeout(15)->get($sourceUrl);

            if (!$response->successful()) {
                Log::warning("OfficialPhotoOptimizer: resposta não-OK ({$response->status()}) de {$sourceUrl}");

                return null;
            }

            // Decodificar a imagem original crua (ex: a da ALECE, 4MB
            // comprimidos) pode precisar de bem mais que os 128MB padrão do
            // PHP — o GD descompacta tudo em memória antes de redimensionar.
            // Isso é um Fatal Error, não uma Exception: sem aumentar aqui,
            // o try/catch abaixo nem pegaria, e o processo todo morreria.
            $memoryLimitAntes = ini_get('memory_limit');
            ini_set('memory_limit', '512M');

            try {
                $manager = ImageManager::usingDriver(GdDriver::class);
                $image = $manager->decodeBinary($response->body());
                $image->scaleDown(width: 400, height: 400);
                $encoded = $image->encodeUsingFormat(Format::JPEG, quality: 82);

                Storage::disk($disk)->put($storagePath, (string) $encoded);
            } finally {
                ini_set('memory_limit', $memoryLimitAntes);
            }

            $publicUrl = config("filesystems.disks.{$disk}.public_url");

            return rtrim((string) $publicUrl, '/').'/'.$storagePath;
        } catch (Throwable $e) {
            Log::warning("OfficialPhotoOptimizer: falha ao processar {$sourceUrl}: {$e->getMessage()}");

            return null;
        }
    }
}
