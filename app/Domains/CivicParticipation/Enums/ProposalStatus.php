<?php

namespace App\Domains\CivicParticipation\Enums;

enum ProposalStatus: string
{
    case Published = 'published';
    case Draft = 'draft';
    case Removed = 'removed';
}
