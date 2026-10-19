<?php

namespace App\Services\Tse;

use RuntimeException;

/**
 * Falha ao abrir ou interpretar um JSON de resultados do TSE.
 *
 * O `kind()` separa os casos que o comando `tse:import-results` precisa
 * contabilizar de forma distinta: http_403, http_404, http_429, http_5xx,
 * timeout, json (JSON inválido), html (conteúdo HTML inesperado),
 * structure (JSON válido sem a estrutura de resultados) e config
 * (falha ao ler o EA11 `ele-c.json`).
 */
class TseResultsException extends RuntimeException
{
    public function __construct(
        private readonly string $kind,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function kind(): string
    {
        return $this->kind;
    }
}
