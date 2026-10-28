<?php

/*
|--------------------------------------------------------------------------
| Radar do Brasil
|--------------------------------------------------------------------------
| Configuração isolada do Radar. Nada aqui é lido por outra funcionalidade.
|
| Os resultados ficam no cache padrão da aplicação (em produção, o driver
| 'database' = tabela `cache` já existente no Supabase), sempre sob o prefixo
| de chave 'radar:v1:' — nenhuma tabela nova, e nenhuma operação genérica de
| limpeza (flush) é usada pelo Radar.
*/

return [

    // Prefixo de todas as chaves do Radar no cache (evita colisão com
    // candidatos, notícias, universidades etc.).
    'cache_prefix' => 'radar:v1:',

    // Store fixo em 'database' (tabela `cache` do Supabase), independente do
    // CACHE_STORE do ambiente: o resultado do Radar precisa sobreviver a
    // deploy e reinício do Render, e o store 'file' não sobrevive. Os testes
    // trocam para 'array'.
    'cache_store' => 'database',

    // Fila própria: o worker do entrypoint.sh processa 'coleta,resumo,radar'
    // nessa ordem de prioridade, então o Radar nunca passa na frente das
    // notícias pendentes.
    'queue' => 'radar',

    // 57ª legislatura. Os arquivos da Câmara são anuais (ano da proposição),
    // então o recorte é por ano civil de 2023 em diante.
    'legislature' => [
        'number' => 57,
        'start' => '2023-02-01',
        'end' => '2027-01-31',
        'first_year' => 2023,
        'last_year' => 2026,
    ],

    // Tipos de proposição considerados — os mesmos dos sync:bills-* atuais.
    'bill_types' => ['PL', 'PEC'],

    // Depois de quantas horas um resultado publicado é considerado vencido e
    // uma nova atualização é despachada em segundo plano. O resultado
    // vencido continua sendo servido até a atualização terminar e validar.
    'stale_after_hours' => [
        'current_year' => 24,
        'past_years' => 24 * 7,
        'ibge' => 24 * 7,
        // Uma chamada ao Senado por proposição: 3 dias poupa a API.
        'senado' => 72,
    ],

    // Com amostra pequena (o Senado tem 3 senadores por UF), percentual não
    // tem valor estatístico: abaixo disso a UF é sinalizada como amostra
    // pequena e o front mostra só contagens absolutas.
    'small_sample_threshold' => 30,

    'camara' => [
        'authors_url' => 'https://dadosabertos.camara.leg.br/arquivos/proposicoesAutores/csv/proposicoesAutores-{year}.csv',
        'topics_url' => 'https://dadosabertos.camara.leg.br/arquivos/proposicoesTemas/csv/proposicoesTemas-{year}.csv',
        // codTipoAutor de "Deputado(a)" nos arquivos oficiais. Outros tipos
        // (Senado Federal, comissões, Executivo) ficam de fora.
        'deputy_author_type' => '10000',
        // O servidor de arquivos da Câmara derruba a conexão no meio de
        // downloads grandes (medido: 10 retomadas p/ 39 MB) — por isso o
        // download retoma do byte onde parou, até este número de tentativas.
        'download_attempts' => 40,
        'download_timeout' => 120,
        // Proteção contra arquivo truncado/incompleto: um novo cálculo com
        // menos da metade das proposições do resultado já publicado para o
        // mesmo ano é rejeitado (o anterior continua no ar).
        'min_ratio_vs_previous' => 0.5,
    ],

    'senado' => [
        // Pausa entre chamadas. Era 300 ms (igual a sync:bill-topics), mas o
        // Senado passou a responder 429 "Too Many Requests" com várias UFs
        // sendo atualizadas em sequência; 1 s alivia o volume.
        'sleep_ms' => 1000,

        // Esperas entre novas tentativas (uma por item; acabou a lista, a
        // falha vale). 429 = limite de volume do Senado: esperas longas.
        // 5xx/conexão = instabilidade passageira: esperas curtas.
        'backoff_429_seconds' => [20, 60, 120],
        'backoff_seconds' => [2, 5, 10],

        // Teto de tempo para novas tentativas numa coleta (o job tem 30 min).
        'retry_budget_seconds' => 1200,
    ],

    'ibge' => [
        'base_url' => 'https://servicodados.ibge.gov.br/api/v3/agregados',
        'indicators' => [
            'population' => [
                'label' => 'População residente estimada',
                'aggregate' => 6579,
                'variable' => 9324,
                'period' => '-1',
                'source' => 'IBGE — Estimativas de População',
            ],
            'area' => [
                'label' => 'Área da unidade territorial',
                'aggregate' => 4714,
                'variable' => 6318,
                'period' => '2022',
                'source' => 'IBGE — Censo Demográfico 2022',
            ],
            'gdp' => [
                'label' => 'Produto Interno Bruto a preços correntes',
                'aggregate' => 5938,
                'variable' => 37,
                'period' => '-1',
                'source' => 'IBGE — Contas Regionais',
            ],
        ],
    ],
];
