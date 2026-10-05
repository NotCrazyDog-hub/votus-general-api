<?php

namespace Tests\Feature\Tse;

use Illuminate\Support\Facades\Http;

/**
 * Payloads de exemplo com o formato real da API DivulgaCandContas.
 *
 * Evitar "arrays mágicos" repetidos nos testes deixa explícito o que cada
 * cenário exercita (foto, plano de governo, vices, histórico).
 */
trait FakeTseApi
{
    /**
     * Detalhe devolvido pelo stub do endpoint de busca.
     *
     * Precisa ser estado mutável: `Http::fake()` ACUMULA os stubs registrados
     * (merge), então um segundo `fake()` não substitui o anterior — o
     * callback mais antigo continuaria respondendo. Trocar o conteúdo desta
     * variável é a forma de simular "a API mudou" entre duas sincronizações.
     *
     * @var array<string, mixed>
     */
    protected array $fakeDetail = [];

    /**
     * Payload por SQ_CANDIDATO, lido a cada requisição do stub.
     *
     * `fakeCandidates()` capturava um `use ($byId)` por VALOR no closure, então
     * `setDetail()` não tinha efeito na 2ª sincronização — o stub continuava
     * devolvendo o payload antigo. Mantendo o mapa aqui (mutável), a troca de
     * detalhe chega na rede de verdade.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $fakeDetails = [];

    /**
     * IDs cujo endpoint de detalhe deve responder 404.
     *
     * Também precisa ser estado mutável: registrar um `Http::fake()` depois do
     * `fakeCandidates()` não sobrescreve nada — `Factory::fake()` faz MERGE dos
     * stubs e o callback mais antigo (o catch-all de `/buscar/`) responde
     * primeiro. Para o stub importar, a decisão tem que acontecer DENTRO daquele
     * callback.
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

        $listing = [];
        foreach ($details as $detail) {
            $listing[] = ['id' => $detail['id'], 'nomeUrna' => $detail['nomeUrna']];
        }

        Http::fake([
            '*/candidatura/listar/*' => fn () => Http::response(
                $this->candidateList($listing),
            ),
            '*/candidatura/buscar/*' => function ($request) {
                $segments = explode('/', (string) parse_url($request->url(), PHP_URL_PATH));
                $id = (string) end($segments);

                if (in_array($id, $this->fake404Ids, true)) {
                    return Http::response([], 404);
                }

                // Estado MUTÁVEL: setDetail() tem que surtir efeito aqui.
                return Http::response(
                    $this->fakeDetails[$id] ?? $this->fakeDetail,
                );
            },
            '*/arquivo/img/*' => Http::response($photoBody, $fileStatus),
            '*/arquivo/doc/*' => Http::response($docBody, $fileStatus),
            '*' => Http::response([], 404),
        ]);
    }
}
