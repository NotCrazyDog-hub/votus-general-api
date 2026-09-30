<?php

namespace App\Services\Metrics;

use App\Models\Legislator;

class LegislativeEffectivenessService
{
    public function calculate(Legislator $legislator): array
    {
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

        $advanced = $bills->filter(fn ($bill) => $this->hasAdvanced($bill))->count();

        return [
            'total' => $total,
            'advanced' => $advanced,
            'rate' => round($advanced / $total, 4),
            'wilson_lower' => $this->wilsonLowerBound($advanced, $total),
        ];
    }

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

        $p = $x / $n;
        $denominator = 1 + ($z ** 2) / $n;
        $center = $p + ($z ** 2) / (2 * $n);
        $margin = $z * sqrt(($p * (1 - $p) / $n) + ($z ** 2) / (4 * $n ** 2));

        return round(($center - $margin) / $denominator, 4);
    }
}