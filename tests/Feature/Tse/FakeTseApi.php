<?php

namespace Tests\Feature\Tse;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * Payloads de exemplo com o formato real da API DivulgaCandContas.
 *
 * Evitar "arrays mágicos" repetidos nos testes deixa explícito o que cada
 * cenário exercita (foto, plano de governo, vices, histórico).
 *
 * ONDE CADA FAKE MORA AGORA
 *
 *  - listagem e detalhe: `Process::fake()` — essas consultas saem pelo
 *    `scripts/tse/fetch-candidates.js`, nunca mais por Http::get(). O stub
 *    devolve o MESMO JSON que o script emite no stdout;
 *  - foto e documento: `Http::fake()` — continuam em Http, então os testes de
 *    arquivo seguem exatamente como antes.
 */
trait FakeTseApi
{
    /**
     * Detalhe devolvido pelo stub da listagem (campo `details`).
     *
     * Precisa ser estado mutável: o closure do `Process::fake()` lê `$this`
     * a cada invocação, então trocar o conteúdo desta variável é a forma de
     * simular "a API mudou" entre duas sincronizações.
     *
     * @var array<string, mixed>
     */
    protected array $fakeDetail = [];

    /**
     * Payload por SQ_CANDIDATO, lido a cada execução do stub.
     *
     * `fakeCandidates()` capturava um `use ($byId)` por VALOR no closure, então
     * `setDetail()` não tinha efeito na 2ª sincronização — o stub continuava
     * devolvendo o payload antigo. Mantendo o mapa aqui (mutável), a troca de
     * detalhe chega ao serviço de verdade.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $fakeDetails = [];

    /**
     * IDs cujo detalhe deve responder `status: 404`.
     *
     * @var array<int, string>
     */
    protected array $fake404Ids = [];

    /**
     * Marca candidatos cujo detalhe deve responder 404.
     *
     * @param  array<int, int|string>  $ids
     */
    protected function failDetails(array $ids): void
    {
        $this->fake404Ids = array_map(strval(...), $ids);
    }

    /**
     * Define o payload do detalhe para as próximas requisições.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function setDetail(array $overrides = []): array
    {
        $this->fakeDetail = array_replace(
            $this->fakeDetail === [] ? $this->candidateDetail() : $this->fakeDetail,
            $overrides,
        );

        // Reindexa o payload corrente: o stub de /buscar/ lê ESTE array a cada
        // requisição, então trocar o detalhe aqui é o que faz o "a API mudou"
        // chegar na segunda sincronização.
        $this->fakeDetails[(string) ($this->fakeDetail['id'] ?? '')] = $this->fakeDetail;

        return $this->fakeDetail;
    }

    /**
     * Payload de detalhe do candidato, com o formato do
     * /candidatura/buscar/{year}/{uf}/{electionId}/candidato/{id}.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function candidateDetail(array $overrides = []): array
    {
        return array_replace([
            'id' => 60002543969,
            'nomeUrna' => 'ELMANO DE FREITAS',
            'numero' => 13,
            'nomeCompleto' => 'ELMANO DE FREITAS DA COSTA',
            'descricaoSexo' => 'Masculino',
            'dataDeNascimento' => '1963-06-21',
            'descricaoEstadoCivil' => 'Casado',
            'descricaoCorRaca' => 'Parda',
            'descricaoSituacao' => 'Deferido',
            'nacionalidade' => 'Brasileira',
            'grauInstrucao' => 'Superior Completo',
            'ocupacao' => 'Economista',
            'gastoCampanha1T' => 1000000,
            'sgUfNascimento' => 'CE',
            'nomeMunicipioNascimento' => 'Fortaleza',
            'localCandidatura' => 'Estadual',
            'ufCandidatura' => 'CE',
            'ufSuperiorCandidatura' => 'CE',
            'dataUltimaAtualizacao' => '2026-08-01T10:00:00',
            'fotoUrl' => 'https://divulgacandcontas.tse.jus.br/divulga/rest/arquivo/img/ALT-60002543969/CE',
            'fotoUrlPublicavel' => 'https://divulgacandcontas.tse.jus.br/divulga/rest/arquivo/img/20322002026/60002543969/CE',
            'descricaoTotalizacao' => null,
            'nomeColigacao' => 'PT',
            'cargo' => [
                'codigo' => 3,
                'sigla' => 'GOV',
                'nome' => 'Governador',
                'codSuperior' => 3,
                'titular' => true,
            ],
            'vices' => null,
            'partido' => [
                'codigo' => 13,
                'sigla' => 'PT',
                'nome' => 'PARTIDO DOS TRABALHADORES',
            ],
            'eleicao' => [
                'id' => 420,
                'ano' => 2026,
                'tipoEleicao' => 'ordinaria',
            ],
            'arquivos' => null,
            'eleicoesAnteriores' => null,
            'codigoSituacaoCandidato' => 2,
        ], $overrides);
    }

    /**
     * Resposta da listagem de candidatos.
     *
     * @param  array<int, array<string, mixed>>  $candidatos
     * @return array<string, mixed>
     */
    protected function candidateList(array $candidatos): array
    {
        return [
            'unidadeEleitoral' => ['sgUe' => 'CE'],
            'cargo' => ['codigo' => 3, 'nome' => 'Governador'],
            'candidatos' => $candidatos,
        ];
    }

    /**
     * Item de `eleicoesAnteriores`, no formato real da API.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function previousElection(array $overrides = []): array
    {
        return array_replace([
            'nrAno' => 2022,
            'id' => '60001633585',
            'nomeUrna' => 'ELMANO DE FREITAS',
            'nomeCandidato' => 'ELMANO DE FREITAS DA COSTA',
            'idEleicao' => '2040602022',
            'sgUe' => 'CE',
            'local' => 'CEARÁ',
            'cargo' => 'Governador',
            'partido' => 'PT',
            'situacaoTotalizacao' => 'Eleito',
            'nrCandidato' => 13,
        ], $overrides);
    }

    /**
     * Fakes das rotas de listagem, detalhe, foto e documento de um candidato.
     *
     * `$fileStatus` simula falha de download (404) para foto e documento.
     *
     * @param  array<string, mixed>  $detail
     */
    protected function fakeCandidateApi(array $detail = [], ?string $photoBody = null, ?string $docBody = null, int $fileStatus = 200): void
    {
        $this->setDetail($detail === [] ? [] : $detail);

        $this->fakeCandidates([$this->fakeDetail], $photoBody, $docBody, $fileStatus);
    }

    /**
     * Fakes a listagem com VÁRIOS candidatos, cada um com seu próprio detalhe.
     *
     * O endpoint de busca devolve o payload correspondente ao id pedido,
     * extraído da URL — assim um titular e um vice podem existir ao mesmo
     * tempo, que é o que o vínculo de running_mates exige.
     *
     * @param  array<int, array<string, mixed>>  $details
     */
    protected function fakeCandidates(array $details, ?string $photoBody = null, ?string $docBody = null, int $fileStatus = 200): void
    {
        $photoBody ??= 'fake-jpeg-bytes';
        $docBody ??= 'fake-pdf-bytes';

        $first = $details[0] ?? $this->candidateDetail();
        $this->fakeDetail = $first;

        $this->fakeDetails = [];
        foreach ($details as $detail) {
            $this->fakeDetails[(string) $detail['id']] = $detail;
        }

        // Listagem + detalhe agora saem de UM processo Node só. `Process::fake`
        // sobrescreve o handler anterior (mesma chave '*'), então rechamar
        // `fakeCandidates()` troca o retorno sem acumular stubs — e o closure
        // relê `$this` a cada invocação, que é o que faz `setDetail()` entre
        // duas sincronizações chegar ao serviço.
        Process::fake([
            '*' => fn () => Process::result(output: $this->tseBatchOutput()),
        ]);

        // Só arquivos batem em Http hoje; o catch-all 404 é a garantia de que
        // nenhuma listagem/detalhe voltou a usar Http::get() por engano.
        Http::fake([
            '*/arquivo/img/*' => Http::response($photoBody, $fileStatus),
            '*/arquivo/doc/*' => Http::response($docBody, $fileStatus),
            '*' => Http::response([], 404),
        ]);
    }

    /**
     * Monta o stdout que o `fetch-candidates.js` emitiria para o lote corrente.
     *
     * É o mesmo formato do contrato: `{"success":true,"status":200,"data":{...}}`
     * com `list` e `details`. Os `fake404Ids` entram como entrada de detalhe
     * com `status` 404, que é como o script reporta um HTTP != 200.
     */
    private function tseBatchOutput(): string
    {
        $listing = [];
        $details = [];

        foreach ($this->fakeDetails as $id => $detail) {
            $listing[] = ['id' => $detail['id'], 'nomeUrna' => $detail['nomeUrna'] ?? null];

            // As chaves do mapa viram int para IDs numéricos, mas
            // `failDetails()` guarda strings: compara como string.
            $details[$id] = in_array((string) $id, $this->fake404Ids, true)
                ? ['status' => 404, 'error' => 'TSE_HTTP_404']
                : ['status' => 200, 'data' => $detail];
        }

        return (string) json_encode([
            'success' => true,
            'status' => 200,
            'data' => [
                'list' => $this->candidateList($listing),
                'details' => $details,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Faz o processo Node devolver uma saída bruta (para testar o parsing).
     *
     * @param  array<string, mixed>  $payload
     */
    protected function fakeTseJson(array $payload, int $exitCode = 0, string $errorOutput = ''): void
    {
        Process::fake([
            '*' => Process::result(
                output: (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                errorOutput: $errorOutput,
                exitCode: $exitCode,
            ),
        ]);
    }

    /**
     * Faz o processo Node devolver `{"success":false,...}` — o caso em que o
     * script detectou a falha mas conseguiu reportá-la.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function fakeTseFailure(string $error, int $status = 0, string $message = ''): void
    {
        $this->fakeTseJson([
            'success' => false,
            'status' => $status,
            'error' => $error,
            'message' => $message,
        ]);
    }
}
