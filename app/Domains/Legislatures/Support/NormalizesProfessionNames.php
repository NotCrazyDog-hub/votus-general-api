<?php

namespace App\Domains\Legislatures\Support;
use Illuminate\Support\Str;

trait NormalizesProfessionNames
{
    protected function normalizeProfessionName(string $name): string
    {
        $name = preg_replace('/\(a\)/i', '', $name);
        $name = trim($name);
        $name = Str::ascii($name);

        return mb_strtolower($name);
    }
}