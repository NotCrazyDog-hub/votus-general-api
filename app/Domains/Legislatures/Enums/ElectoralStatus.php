<?php

namespace App\Domains\Legislatures\Enums;

enum ElectoralStatus: string
{
    case Sitting = 'sitting';
    case Alternate = 'alternate';
    case Unknown = 'unknown';
}