<?php

namespace App\Domains\Legislatures\Services\Metrics;

use App\Domains\Legislatures\Models\Legislator;
use App\Domains\Legislatures\Models\LegislaturePeriod;
use App\Domains\Legislatures\Services\Senate\SenateApiService;
use Carbon\Carbon;

class LegislativeProductivityService
{
    public function __construct(protected SenateApiService $senateApi) {}

    /** Calcula proposições por ano de mandato; retorna null quando a data é desconhecida ou a amostra é curta. */
    public function calculate(Legislator $legislator): array
    {
        $mandateStartedAt = $this->resolveMandateStart($legislator);

        if ($mandateStartedAt === null) {
            return [
                'mandate_started_at' => null,
                'bills_per_year' => null,
            ];
        }

        $totalBills = $legislator->bills()->count();
        // Usa 365,25 para aproximar anos civis incluindo anos bissextos.
        $yearsInOffice = Carbon::parse($mandateStartedAt)->diffInDays(now()) / 365.25;

        if ($yearsInOffice < 0.25) {
            // Períodos menores que três meses geram uma taxa anualizada instável.
            return [
                'mandate_started_at' => $mandateStartedAt,
                'bills_per_year' => null,
            ];
        }

        return [
            'mandate_started_at' => $mandateStartedAt,
            'bills_per_year' => round($totalBills / $yearsInOffice, 2),
        ];
    }

    /** Obtém o início do mandato pela API do Senado ou pela tabela de legislaturas das outras casas. */
    protected function resolveMandateStart(Legislator $legislator): ?string
    {
        if ($legislator->chamber === 'senate') {
            // No Senado, a API fornece a data individual de início do mandato;
            // nas demais casas, a data é compartilhada pela legislatura inteira.
            return $this->senateApi->getMandateStartDate($legislator->external_id);
        }

        $period = LegislaturePeriod::where('legislature_number', $legislator->legislature)
            ->where('chamber', $legislator->chamber)
            ->first();

        return $period?->starts_at?->toDateString();
    }
}