<?php

namespace App\Enums;

enum ProposalStatus: string
{
    case Published = 'published';
    case Draft = 'draft';
}
