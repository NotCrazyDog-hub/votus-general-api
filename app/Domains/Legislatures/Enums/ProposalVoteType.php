<?php

namespace App\Domains\Legislatures\Enums;

enum ProposalVoteType: string
{
    case Legal = 'legal';
    case NotSupport = 'not_support';
}
