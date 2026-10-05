<?php

namespace App\Domains\CivicParticipation\Enums;

enum ProposalVoteType: string
{
    case Legal = 'legal';
    case NotSupport = 'not_support';
}
