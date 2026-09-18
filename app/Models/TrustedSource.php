<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrustedSource extends Model
{
    protected $fillable = [
        'name',
        'domain',
        'base_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Normaliza domínio ou URL completa pro mesmo formato salvo em `domain`
     * (sem protocolo, sem www) — usado tanto ao cadastrar uma fonte quanto
     * ao validar se o link que o admin colou numa explicação pertence a uma
     * fonte confiável já cadastrada.
     */
    public static function normalizeDomain(string $value): string
    {
        $value = trim(strtolower($value));

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            $host = parse_url($value, PHP_URL_HOST);

            if ($host) {
                $value = $host;
            }
        }

        return preg_replace('/^www\./', '', $value);
    }
}
