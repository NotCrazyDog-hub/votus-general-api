<?php

namespace App\Domains\Radar\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Baixa um arquivo oficial em lote da Câmara (dadosabertos.camara.leg.br/
 * arquivos/...) para um arquivo temporário local.
 *
 * O servidor de arquivos da Câmara derruba conexões no meio de downloads
 * grandes (medido em 20/10/2026: 39 MB levaram 10 retomadas). Por isso:
 *  - cada tentativa pede só o que falta (cabeçalho Range);
 *  - If-Range com o Last-Modified original: se o arquivo for republicado no
 *    meio do download (a Câmara atualiza diariamente), o servidor manda o
 *    arquivo inteiro de novo (200) em vez de emendar duas versões;
 *  - no fim, o tamanho tem que bater exatamente com o Content-Length.
 * Se não completar dentro das tentativas, lança exceção — nunca devolve um
 * arquivo parcial como se fosse completo.
 */
class CamaraBulkFileDownloader
{
    /**
     * Baixa o arquivo completo, retomando tentativas interrompidas por Range.
     * Confere Content-Length antes de aceitar o resultado e retorna metadados
     * para que a coleta registre a origem e o número de retomadas.
     */
    public function download(string $url, string $destination): array
    {
        $timeout = (int) config('radar.camara.download_timeout', 120);
        $maxAttempts = (int) config('radar.camara.download_attempts', 40);

        $head = Http::timeout(30)->retry(3, 2000)->head($url);

        if (! $head->successful()) {
            throw new RuntimeException("Arquivo indisponível ({$head->status()}): {$url}");
        }

        $expected = (int) $head->header('Content-Length');
        $lastModified = $head->header('Last-Modified') ?: null;

        if ($expected <= 0) {
            throw new RuntimeException("Arquivo sem Content-Length, não dá para validar integridade: {$url}");
        }

        @unlink($destination);
        touch($destination);

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            clearstatcache(true, $destination);
            $offset = filesize($destination);

            if ($offset === $expected) {
                return ['path' => $destination, 'bytes' => $expected, 'last_modified' => $lastModified, 'attempts' => $attempt - 1];
            }

            if ($offset > $expected) {
                throw new RuntimeException("Arquivo maior que o esperado ({$offset} > {$expected}): {$url}");
            }

            $status = null;
            $headers = [];

            if ($offset > 0) {
                $headers['Range'] = "bytes={$offset}-";

                if ($lastModified) {
                    $headers['If-Range'] = $lastModified;
                }
            }

            // Um arquivo de trecho por tentativa: no Windows o da tentativa
            // anterior pode continuar aberto pelo cliente HTTP e não dá para
            // recriá-lo (visto em teste local). A pasta inteira é apagada no
            // fim pelo CamaraRadarCollector.
            $part = "{$destination}.part{$attempt}";

            try {
                Http::timeout($timeout)
                    ->withHeaders($headers)
                    ->withOptions([
                        'sink' => $part,
                        'on_headers' => function (ResponseInterface $response) use (&$status) {
                            $status = $response->getStatusCode();
                        },
                    ])
                    ->get($url);
            } catch (Throwable $e) {
                // Conexão derrubada no meio: aproveita o que chegou, se a
                // resposta era a parte certa do arquivo (decidido abaixo).
                Log::info("[RADAR] Download interrompido ({$attempt}/{$maxAttempts}) em {$url}: {$e->getMessage()}");
            }

            $this->mergePart($destination, $part, $offset, $status);

            // Tentativa que não avançou nada (DNS, recusa, 5xx): espera antes
            // de repetir, senão as tentativas se esgotam em um segundo.
            clearstatcache(true, $destination);
            if (filesize($destination) <= $offset && $status !== 200) {
                $this->pause(min(2 ** min($attempt, 5), 30));
            }
        }

        throw new RuntimeException("Download incompleto após {$maxAttempts} tentativas: {$url}");
    }

    /** Aguarda entre tentativas que não trouxeram novos bytes. */
    protected function pause(int $seconds): void
    {
        sleep($seconds);
    }

    /**
     * 206 = veio o trecho pedido -> anexa. 200 = veio o arquivo inteiro (do
     * início, ou porque If-Range detectou nova versão) -> substitui. Qualquer
     * outro status (ou nenhum) -> descarta o trecho e tenta de novo.
     */
    private function mergePart(string $destination, string $part, int $offset, ?int $status): void
    {
        if (! is_file($part)) {
            return;
        }

        if ($status === 200) {
            rename($part, $destination);

            return;
        }

        if ($status === 206 && $offset > 0) {
            $in = fopen($part, 'rb');
            $out = fopen($destination, 'ab');
            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);
        }

        @unlink($part);
    }
}
