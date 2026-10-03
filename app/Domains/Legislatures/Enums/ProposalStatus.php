<?php

namespace App\Domains\Legislatures\Enums;

enum ProposalStatus: string
{
    case Published = 'published';
    case Draft = 'draft';
    case Removed = 'removed';
}
