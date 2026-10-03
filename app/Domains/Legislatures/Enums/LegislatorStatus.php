<?php

namespace App\Domains\Legislatures\Enums;

enum LegislatorStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case OnLeave = 'on_leave';
    case Former = 'former';
    case Unknown = 'unknown';
}