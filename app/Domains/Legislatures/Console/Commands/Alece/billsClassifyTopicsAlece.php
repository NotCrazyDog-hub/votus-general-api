<?php

namespace App\Domains\Legislatures\Console\Commands\Alece;

use App\Domains\Legislatures\Models\Bill;
use App\Services\VotusTopicClassificationService;
use Illuminate\Console\Command;

class BillsClassifyTopicsAlece extends Command
{
    protected $signature = 'bills:classify-topics-alece
        {--limit= : Limitar a quantidade de proposições processadas}
        {--only-unclassified : Processar somente proposições ainda não classificadas}
        {--force : Reclassificar proposições já classificadas}';
    protected $description = 'Classifica proposições da Alece nos temas Votus usando IA Groq';

    public function handle(VotusTopicClassificationService $classifier)
    {
        $bills = Bill::where('chamber', 'state_house')->get();

        if ($this->option('only-unclassified')) {
            $bills = $bills->reject(fn (Bill $bill) =>
                $classifier->jaClassificado($bill, $this->extractContent($bill))
            );
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $bills = $bills->take($limit);
        }

        $total = $bills->count();

        if ($total === 0) {
            $this->info('Nenhuma proposição encontrada para processar.');
            return self::SUCCESS;
        }

        $this->info("Proposições selecionadas para processamento: {$total}");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $stats = [
            'total' => 0,
            'classified_with_topics' => 0,
            'classified_empty' => 0,
            'already_classified' => 0,
            'failed' => 0,
        ];

        foreach ($bills as $bill) {
            try {
                $stats['total']++;

                // Verificar se já foi classificado (conteúdo não mudou)
                if (!$this->option('force') && $classifier->jaClassificado($bill, $this->extractContent($bill))) {
                    $stats['already_classified']++;
                    continue;
                }

                $result = $classifier->classify($bill);

                if (!$result['classified']) {
                    $stats['failed']++;
                    continue;
                }

                if ($result['skipped']) {
                    // Classificação vazia válida ou já classificado
                    $stats['classified_empty']++;
                    continue;
                }

                if (!empty($result['topics'])) {
                    $stats['classified_with_topics']++;
                } else {
                    $stats['classified_empty']++;
                }
            } catch (\Throwable $e) {
                $stats['failed']++;
            } finally {
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("Processamento concluído:");
        $this->info(sprintf(
            "Total: %d | Com temas: %d | Sem temas: %d | Já classificadas: %d | Falhas: %d",
            $stats['total'],
            $stats['classified_with_topics'],
            $stats['classified_empty'],
            $stats['already_classified'],
            $stats['failed']
        ));
    }

    private function extractContent(Bill $bill): string
    {
        $summary = trim($bill->summary ?? '');
        if ($summary !== '') {
            return $summary;
        }

        $rawData = $bill->raw_data ?? [];
        if (is_array($rawData)) {
            $detailSummary = trim($rawData['summary'] ?? '');
            if ($detailSummary !== '') {
                return $detailSummary;
            }
            $summaryShort = trim($rawData['summary_short'] ?? '');
            if ($summaryShort !== '') {
                return $summaryShort;
            }
        }
        return '';
    }
}
