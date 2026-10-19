<?php

namespace App\Services\Tse;

use Illuminate\Support\Facades\Log;

/**
 * Cliente da API REST do DivulgaCandContas (TSE).
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
 *
 * DOIS CAMINHOS DE REDE, PROPOSITALMENTE DIFERENTES:
 *
 *  - listagem e detalhe passam pelo `TseBrowserGateway`, que dispara o
 *    `scripts/tse/fetch-candidates.js` (Playwright, janela headful sob Xvfb).
 *    O TSE/Akamai devolve 403 para cURL/PHP — não há header que resolva, o
 *    bloqueio é por cliente — então essas duas consultas precisam sair de um
 *    Chromium de verdade. É a única forma conhecida de voltar a ler o portal.
 *
 *  - fotos e documentos também saem pelo Chromium, em lote
 *    (`scripts/tse/fetch-files.js`): `/rest/arquivo` responde 403 ao
 *    cURL/PHP, igual à listagem e ao detalhe. Os binários voltam em base64
 *    e ficam em `$fileCache` até serem consumidos por `download()`.
 *
 * A listagem dispara o navegador UMA vez para o lote inteiro (lista + todos os
 * detalhes no mesmo contexto) — `CandidateSyncService` continua chamando
 * `listCandidates()` e depois `getCandidate()` por candidato, sem saber que
 * rede alguma aconteceu no meio.
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
     * Endereço do lote carregado em `$batch`.
     *
     * Guardar a chave (ano|uf|eleição|cargo) é o que impede um segundo sync de
     * outro cargo enxergar os detalhes do primeiro — e, ao mesmo tempo, deixa
     * `getCandidate()` reusar o lote sem refazer a chamada ao Node.
     */
    private ?string $batchKey = null;

    /**
     * Lote corrente: `list` é o payload de `/candidatura/listar/...` e
     * `details` é um mapa `id => {status, data}` vindo do mesmo navegador.
     *
     * @var array{list: array<string, mixed>, details: array<string, array<string, mixed>>}
     */
    private array $batch = ['list' => [], 'details' => []];

    public function __construct(private readonly TseBrowserGateway $browser)
    {
    }

    /**
     * Lista os candidatos de um cargo numa UF.
     *
     * Só para descoberta de IDs — vários campos vêm null aqui. A chamada ao
     * Chromium também traz o detalhe de cada candidato listado, guardado em
     * `$batch` para `getCandidate()` consumir sem rede nova.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listCandidates(int $year, string $uf, string $electionId, int $cargoCode): array
    {
        $this->loadBatch($year, $uf, $electionId, $cargoCode);

        $candidatos = $this->batch['list']['candidatos'] ?? [];

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
        $id = (string) $candidateId;
        $path = "/candidatura/buscar/{$year}/{$uf}/{$electionId}/candidato/{$id}";

        // Veio no lote da listagem? Lê do cache — nenhum processo Node novo.
        // Se não veio (serviço usado isoladamente), busca SÓ este candidato:
        // ainda assim pelo navegador, nunca pelo Http::get() bloqueado.
        if (! array_key_exists($id, $this->batch['details'])) {
            $details = $this->browserRequest([
                'details' => [['key' => $id, 'url' => $this->baseUrl().$path]],
            ])['details'] ?? [];

            $this->batch['details'][$id] = is_array($details[$id] ?? null) ? $details[$id] : [];
        }

        return $this->unwrapDetail($this->batch['details'][$id], $id, $path);
    }

    /**
     * Dispara o lote (lista + todos os detalhes) numa única execução do
     * Chromium, guardando o resultado para os `getCandidate()` seguintes.
     */
    private function loadBatch(int $year, string $uf, string $electionId, int $cargoCode): void
    {
        $key = implode('|', [$year, $uf, $electionId, $cargoCode]);

        if ($this->batchKey === $key) {
            return;
        }

        $listPath = "/candidatura/listar/{$year}/{$uf}/{$electionId}/{$cargoCode}/candidatos";

        try {
            $data = $this->browserRequest([
                'listUrl' => $this->baseUrl().$listPath,
                // {id} é substituído pelo script a partir dos IDs da listagem:
                // o navegador faz a lista e os detalhes no mesmo contexto.
                'detailUrlTemplate' => $this->baseUrl()
                    ."/candidatura/buscar/{$year}/{$uf}/{$electionId}/candidato/{id}",
            ]);
        } catch (TseGatewayException $exception) {
            throw $exception->inContext($listPath);
        }

        $this->batchKey = $key;
        $this->batch = [
            'list' => is_array($data['list'] ?? null) ? $data['list'] : [],
            'details' => is_array($data['details'] ?? null) ? $data['details'] : [],
        ];
    }

    /**
     * Converte a entrada `{status, data}` de um detalhe no payload da API.
     *
     * Um 404 aqui não derruba a sincronização — é `CandidateSyncService` que
     * conta a falha e segue para o próximo candidato. A mensagem reproduz a
     * da antiga versão em `Http::get()`, que os testes já esperam.
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function unwrapDetail(array $entry, string $id, string $path): array
    {
        $status = $entry['status'] ?? null;

        if ($status !== 200) {
            $exception = new TseGatewayException(
                (string) ($entry['error'] ?? 'TSE_HTTP_ERROR'),
                is_int($status) ? $status : null,
                $status === null
                    ? sprintf('Sem detalhe do candidato %s', $id)
                    : sprintf('TSE DivulgaCandContas respondeu %d', $status),
            );

            throw $exception->inContext($path);
        }

        $data = $entry['data'] ?? null;

        if (! is_array($data)) {
            throw new TseGatewayException(
                'INVALID_JSON',
                200,
                "Resposta inválida da API do TSE em {$path}: JSON inesperado.",
            );
        }

        return $data;
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

    /** @var array<string, string|null> */
    private array $fileCache = [];

    public function documentUrl(int|string $idArquivo): string
    {
        return sprintf('%s/doc/%s', $this->arquivoUrl(), $idArquivo);
    }

    /**
     * Baixa um documento pelo idArquivo (padrão /rest/arquivo/doc/{id}).
     */
    public function downloadDocument(int|string $idArquivo): ?string
    {
        return $this->download($this->documentUrl($idArquivo));
    }

    /** @param array<int, ?string> $urls */
    public function prefetchFiles(array $urls): void
    {
        $pending = array_values(array_unique(array_filter(
            $urls,
            fn ($u) => is_string($u) && $u !== '' && ! array_key_exists($u, $this->fileCache),
        )));

        if ($pending === []) {
            return;
        }

        $files = [];
        foreach ($pending as $i => $url) {
            $files[] = ['key' => (string) $i, 'url' => $url];
        }

        try {
            $data = $this->browser->request(
                array_merge($this->browser->browserOptions(), ['files' => $files]),
                'scripts/tse/fetch-files.js',
            );
        } catch (TseGatewayException $e) {
            Log::warning("DivulgaCandContas: falha no lote de arquivos: {$e->getMessage()}");
            foreach ($pending as $url) {
                $this->fileCache[$url] = null;
            }

            return;
        }

        foreach ($pending as $i => $url) {
            $entry = $data['files'][(string) $i] ?? null;
            $bin = is_array($entry) && ($entry['status'] ?? null) === 200 && ! empty($entry['base64'])
                ? base64_decode($entry['base64'], true)
                : false;

            if ($bin === false || $bin === '') {
                Log::warning('DivulgaCandContas: arquivo indisponível (status '.($entry['status'] ?? 'n/d')."): {$url}");
                $this->fileCache[$url] = null;

                continue;
            }

            $this->fileCache[$url] = $bin;
        }
    }

    /**
     * Baixa um arquivo qualquer da API e devolve o binário, ou null em caso
     * de falha — foto e documento nunca derrubam a sincronização.
     */
    private function download(string $url): ?string
    {
        $this->prefetchFiles([$url]);          // no-op se já veio no lote

        $content = $this->fileCache[$url] ?? null;
        unset($this->fileCache[$url]);         // libera memória

        return $content;
    }

    /**
     * Repassa a consulta ao gateway, acrescentando as opções comuns do
     * navegador (URL de partida, modo headless, timeouts).
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     *
     * @throws TseGatewayException
     */
    private function browserRequest(array $extra): array
    {
        return $this->browser->request(array_merge($this->browser->browserOptions(), $extra));
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
