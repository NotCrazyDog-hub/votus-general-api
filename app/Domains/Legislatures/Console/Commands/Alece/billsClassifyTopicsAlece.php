<?php

namespace App\Domains\Legislatures\Console\Commands\Alece;

use App\Domains\Legislatures\Models\Bill;
use App\Services\VotusTopicClassificationService;
use Illuminate\Console\Command;

class BillsClassifyTopicsAlece extends Command
{
    protected $signature = 'bills:classify-topics-alece';
    protected $description = 'Classifica proposições da Alece nos temas Votus usando IA Groq';

    public function handle(VotusTopicClassificationService $classifier)
    {
        $bills = Bill::where('chamber', 'state_house')->get();

        $stats = [
            'total' => 0,
            'classified_with_topics' => 0,
            'classified_empty' => 0,
            'already_classified' => 0,
            'failed' => 0,
        ];

        foreach ($bills as $bill) {
            $stats['total']++;

            // Verificar se já foi classificado (conteúdo não mudou)
            if ($classifier->isAlreadyClassified($bill, $this->extractContent($bill))) {
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
        }

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