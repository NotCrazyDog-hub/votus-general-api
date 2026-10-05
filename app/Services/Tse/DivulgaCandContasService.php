<?php

namespace App\Services\Tse;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente HTTP da API REST do DivulgaCandContas (TSE).
 *
 * Substitui os CSVs de Dados Abertos como fonte de candidatos. Toda a parte
 * de rede — URL base, montagem de endpoint, timeout, retry e tratamento de
 * erro — fica aqui, para que nenhuma outra camada precise saber o formato da
 * URL ou tratar exceção do TSE por conta própria.
 *
 * A listagem é usada só para descobrir os IDs; a persistência usa sempre o
 * endpoint de detalhes, que é a fonte completa. Vários campos vêm `null` na
 * listagem (CPF, escolaridade, ocupação...), então tratá-la como definitiva
 * gravaria dado incompleto no banco.
 */
class DivulgaCandContasService
{
    /**
     * Códigos de cargo do TSE na eleição de 2026.
     *
     * @var array<string, int>
     */
    public const CARGO_CODES = [
        'president' => 1,
        'governor' => 3,
        'senator' => 5,
        'federal_deputy' => 6,
        'state_deputy' => 7,
    ];

    /**
     * codTipo do arquivo que é o plano de governo, dentro de `arquivos`.
     */
    public const ARQUIVO_PLANO_DE_GOVERNO = '5';

    /**
     * Lista os candidatos de um cargo numa UF.
     *
     * Só para descoberta de IDs — vários campos vêm null aqui.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listCandidates(int $year, string $uf, string $electionId, int $cargoCode): array
    {
        $payload = $this->get(
            "/candidatura/listar/{$year}/{$uf}/{$electionId}/{$cargoCode}/candidatos",
        );

        $candidatos = $payload['candidatos'] ?? [];

        // Em resposta válida a API sempre devolve uma lista; se vier
        // qualquer outra coisa, é payload inesperado — melhor falhar aqui do
        // que persistir metade dos candidatos silenciosamente.
        return is_array($candidatos) ? array_values($candidatos) : [];
    }

    /**
     * Detalhes completos de um candidato — fonte de verdade da persistência.
     *
     * @return array<string, mixed>
     */
    public function getCandidate(int $year, string $uf, string $electionId, int|string $candidateId): array
    {
        return $this->get("/candidatura/buscar/{$year}/{$uf}/{$electionId}/candidato/{$candidateId}");
    }

    /**
     * Baixa a foto (fotoUrl) e devolve o binário, ou null se não houver.
     *
     * Devolve conteúdo, não caminho: quem chama decide em qual disco grava.
     */
    public function downloadPhoto(?string $photoUrl): ?string
    {
        if (blank($photoUrl)) {
            return null;
        }

        return $this->download($photoUrl);
    }

    /**
     * Baixa um documento pelo idArquivo (padrão /rest/arquivo/doc/{id}).
     */
    public function downloadDocument(int|string $idArquivo): ?string
    {
        $url = sprintf(
            '%s/doc/%s',
            rtrim($this->arquivoUrl(), '/'),
            $idArquivo,
        );

        return $this->download($url);
    }

    /**
     * Baixa um arquivo qualquer da API e devolve o binário, ou null em caso
     * de falha — foto e documento nunca derrubam a sincronização.
     */
    private function download(string $url): ?string
    {
        try {
            $response = $this->request()->get($url);

            if (! $response->successful()) {
                return null;
            }

            $body = $response->body();

            return $body === '' ? null : $body;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $path): array
    {
        $response = $this->request()->get($this->baseUrl().$path);

        if ($response->failed()) {
            throw new RuntimeException(
                sprintf('TSE DivulgaCandContas respondeu %d em %s', $response->status(), $path)
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException("Resposta inválida da API do TSE em {$path}: JSON inesperado.");
        }

        return $payload;
    }

    /**
     * Requisição com o comportamento comum a toda a API.
     *
     * O retry só vale para 5xx e erro de conexão: um 4xx (candidato inexistente,
     * UF inválida) é definitivo e repetir só gastaria tempo — mas um 429
     * (rate limit) precisa de nova tentativa, então entra no grupo.
     */
    private function request(): PendingRequest
    {
        $config = config('services.tse.divulgacandcontas');

        return Http::withHeaders([
            'Accept' => 'application/json',
            'User-Agent' => $config['user_agent'],
        ])
            ->timeout($config['timeout'])
            ->connectTimeout(min(10, $config['timeout']))
            ->retry(
                $config['retries'],
                $config['retry_delay'],
                function ($response, $exception) {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    if ($response instanceof Response) {
                        return $response->status() >= 500
                            || $response->status() === 429;
                    }

                    return false;
                },
                throw: false,
            );
    }

    private function baseUrl(): string
    {
        return rtrim(config('services.tse.divulgacandcontas.base_url'), '/');
    }

    private function arquivoUrl(): string
    {
        return rtrim(config('services.tse.divulgacandcontas.arquivo_url'), '/');
    }
}
