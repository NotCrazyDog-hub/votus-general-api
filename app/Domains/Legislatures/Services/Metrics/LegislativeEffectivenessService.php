<?php

namespace App\Domains\Legislatures\Services\Metrics;

use App\Domains\Legislatures\Models\Legislator;

class LegislativeEffectivenessService
{
    /**
     * Conta proposições que avançaram conforme o critério da casa legislativa
     * e retorna total, taxa simples e limite inferior de Wilson.
     */
    public function calculate(Legislator $legislator): array
    {
        // Carrega as tramitações junto com as proposições para que hasAdvanced
        // possa avaliar o conjunto sem uma consulta extra por proposição.
        $bills = $legislator->bills()->with('tramitations')->get();

        $total = $bills->count();

        if ($total === 0) {
            return [
                'total' => 0,
                'advanced' => 0,
                'rate' => null,
                'wilson_lower' => null,
            ];
        }

        // O critério de avanço depende da Câmara de origem, então cada projeto
        // é avaliado pelo método hasAdvanced antes de calcular a taxa geral.
        $advanced = $bills->filter(fn ($bill) => $this->hasAdvanced($bill))->count();

        return [
            'total' => $total,
            'advanced' => $advanced,
            'rate' => round($advanced / $total, 4),
            'wilson_lower' => $this->wilsonLowerBound($advanced, $total),
        ];
    }

    /**
     * Aplica o critério de avanço adequado à casa: ações de tramitação na Câmara
     * e Senado, ou número de lei disponível para a ALECE.
     */
    protected function hasAdvanced($bill): bool
    {
        if ($bill->chamber === 'lower_house') {
            $advancedActions = config('legislative_metrics.effectiveness.lower_house_advanced_actions');

            return $bill->tramitations->contains(
                fn ($t) => in_array($t->action_description, $advancedActions, true)
            );
        }

        if ($bill->chamber === 'state_house') {
            // Sem histórico de tramitação granular nesse scraper — só temos o
            // status final. "Avançou" aqui significa "virou lei", que é um
            // critério mais rígido do que os das outras câmaras (que capturam
            // progresso parcial, tipo aprovação em comissão). Os números não
            // são diretamente comparáveis entre câmaras por causa dessa diferença.
            $lawNumber = $bill->raw_data['law_number'] ?? null;

            return !empty($lawNumber) && $lawNumber !== '-';
        }

        $advancedCodes = config('legislative_metrics.effectiveness.senate_advanced_status_codes');

        return $bill->tramitations->contains(
            fn ($t) => in_array($t->status_code, $advancedCodes, true)
        );
    }

    protected function wilsonLowerBound(int $x, int $n, float $z = 1.96): float
    {
        if ($n === 0) {
            return 0.0;
        }

        // Limite inferior de Wilson: uma estimativa conservadora da taxa que
        // reduz o excesso de confiança quando há poucas proposições na amostra.
        $p = $x / $n;
        $denominator = 1 + ($z ** 2) / $n;
        $center = $p + ($z ** 2) / (2 * $n);
        $margin = $z * sqrt(($p * (1 - $p) / $n) + ($z ** 2) / (4 * $n ** 2));

        return round(($center - $margin) / $denominator, 4);
    }
}