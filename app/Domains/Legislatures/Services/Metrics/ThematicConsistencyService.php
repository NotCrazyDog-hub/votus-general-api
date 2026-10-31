<?php

namespace App\Domains\Legislatures\Services\Metrics;

use App\Domains\Legislatures\Models\CommitteeTopic;
use App\Domains\Legislatures\Models\Legislator;

class ThematicConsistencyService
{
    /**
     * Calcula quantas proposições do parlamentar compartilham pelo menos um
     * tema com suas comissões e retorna contagem, taxa e limite inferior.
     */
    public function calculate(Legislator $legislator): array
    {
        $committeeIds = $legislator->committees()->pluck('committees.id');

        // Reúne os temas associados às comissões do parlamentar; eles formam
        // o conjunto de referência para avaliar o foco de cada proposição.
        $relevantTopicIds = CommitteeTopic::whereIn('committee_id', $committeeIds)
            ->pluck('topic_id');

        $bills = $legislator->bills()->with('topics')->get();
        $total = $bills->count();

        if ($total === 0) {
            return [
                'total' => 0,
                'consistent' => 0,
                'rate' => null,
                'wilson_lower' => null,
            ];
        }

        // Uma proposição é considerada consistente quando pelo menos um tema
        // dela também aparece entre os temas das comissões do parlamentar.
        $consistent = $bills->filter(function ($bill) use ($relevantTopicIds) {
            return $bill->topics->pluck('id')->intersect($relevantTopicIds)->isNotEmpty();
        })->count();

        return [
            'total' => $total,
            'consistent' => $consistent,
            'rate' => round($consistent / $total, 4),
            'wilson_lower' => $this->wilsonLowerBound($consistent, $total),
        ];
    }

    protected function wilsonLowerBound(int $x, int $n, float $z = 1.96): float
    {
        if ($n === 0) {
            return 0.0;
        }

        // Usa o limite inferior de Wilson para expor uma taxa conservadora,
        // menos instável que a proporção simples quando há poucos projetos.
        $p = $x / $n;
        $denominator = 1 + ($z ** 2) / $n;
        $center = $p + ($z ** 2) / (2 * $n);
        $margin = $z * sqrt(($p * (1 - $p) / $n) + ($z ** 2) / (4 * $n ** 2));

        return round(($center - $margin) / $denominator, 4);
    }
}