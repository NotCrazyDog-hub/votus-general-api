<?php

namespace App\Enums;

enum CandidateOffice: string
{
    case Governor = 'governor';
    case Senator = 'senator';
    case FederalDeputy = 'federal_deputy';
    case StateDeputy = 'state_deputy';

    public static function fromTseDescription(string $dsCargo): ?self
    {
        return match (mb_strtoupper(trim($dsCargo))) {
            'GOVERNADOR' => self::Governor,
            'SENADOR' => self::Senator,
            'DEPUTADO FEDERAL' => self::FederalDeputy,
            'DEPUTADO ESTADUAL' => self::StateDeputy,
            default => null,
        };
    }

    public function toTseDescription(): string
    {
        return match ($this) {
            self::Governor => 'GOVERNADOR',
            self::Senator => 'SENADOR',
            self::FederalDeputy => 'DEPUTADO FEDERAL',
            self::StateDeputy => 'DEPUTADO ESTADUAL',
        };
    }
}