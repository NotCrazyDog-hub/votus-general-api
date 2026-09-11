<?php

namespace App\Services\Metrics;

use App\Models\Legislator;
use App\Models\LegislaturePeriod;
use App\Services\SenateApiService;
use Carbon\Carbon;

class LegislativeProductivityService
{
    public function __construct(protected SenateApiService $senateApi) {}

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
        $yearsInOffice = Carbon::parse($mandateStartedAt)->diffInDays(now()) / 365.25;

        if ($yearsInOffice < 0.25) {
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

    protected function resolveMandateStart(Legislator $legislator): ?string
    {
        if ($legislator->chamber === 'senate') {
            return $this->senateApi->getMandateStartDate($legislator->external_id);
        }

        $period = LegislaturePeriod::where('legislature_number', $legislator->legislature)->first();

        return $period?->starts_at?->toDateString();
    }
}