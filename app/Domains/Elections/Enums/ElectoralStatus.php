<?php

namespace App\Domains\Elections\Enums;

enum ElectoralStatus: string
{
    case Sitting = 'sitting';
    case Alternate = 'alternate';
    case Unknown = 'unknown';
}