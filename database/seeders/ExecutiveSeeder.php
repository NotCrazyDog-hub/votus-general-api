<?php

namespace Database\Seeders;

use App\Models\Executive;
use Illuminate\Database\Seeder;

class ExecutiveSeeder extends Seeder
{
    public function run(): void
    {
        $executives = [
            [
                'name' => 'Elmano de Freitas da Costa',
                'display_name' => 'Elmano de Freitas',

                'office' => 'governor',
                'level' => 'state',
                'state' => 'CE',

                'party_acronym' => 'PT',
                'party_name' => 'Partido dos Trabalhadores',

                'birth_date' => '1970-04-12',
                'birth_place' => 'Baturité, CE',
                'occupation' => 'Governador',
                'education' => 'Graduado em Direito pela Universidade Federal do Ceará (UFC).',

                'biography' => 'Elmano de Freitas da Costa nasceu em 12 de abril de 1970, em Baturité, Ceará. É graduado em Direito pela Universidade Federal do Ceará (UFC). Foi secretário de Educação de Fortaleza e coordenador do Orçamento Participativo da Prefeitura de Fortaleza. Foi eleito deputado estadual em 2014 e reeleito em 2018. Em 2022, foi eleito governador do Ceará, iniciando o mandato em 1º de janeiro de 2023.',

                'photo_path' => null,

                'started_at' => '2023-01-01',
                'ended_at' => '2026-12-31',
                'is_current' => true,

                'source_name' => 'Governo do Estado do Ceará',
                'source_url' => 'https://www.ce.gov.br/o-governo/',

                'external_id' => null,

                'raw_data' => [
                    'sources' => [
                        [
                            'name' => 'Governo do Estado do Ceará',
                            'url' => 'https://www.ce.gov.br/o-governo/',
                        ],
                        [
                            'name' => 'Tribunal Superior Eleitoral',
                            'url' => 'https://www.tse.jus.br/comunicacao/noticias/2022/Outubro/elmano-de-freitas-pt-e-eleito-governador-do-ceara',
                        ],
                    ],
                ],
            ],

            [
                'name' => 'Jade Afonso Romero',
                'display_name' => 'Jade Romero',

                'office' => 'vice_governor',
                'level' => 'state',
                'state' => 'CE',

                'party_acronym' => 'PT',
                'party_name' => 'Partido dos Trabalhadores',

                'birth_date' => '1985-07-12',
                'birth_place' => 'Fortaleza, CE',
                'occupation' => 'Vice-governadora',
                'education' => 'Graduada em Gestão Pública pela Universidade de Fortaleza (Unifor), especialista em Políticas Públicas e mestranda em Planejamento e Políticas Públicas.',

                'biography' => 'Jade Afonso Romero é vice-governadora do Ceará. É graduada em Gestão Pública pela Universidade de Fortaleza (Unifor), especialista em Políticas Públicas e mestranda em Planejamento e Políticas Públicas. Foi secretária-executiva do Esporte do Governo do Ceará, secretária de Participação Popular de Fortaleza e titular da Secretaria das Mulheres entre 2023 e 2024. Em 2025, assumiu a Secretaria da Proteção Social.',

                'photo_path' => null,

                'started_at' => '2023-01-01',
                'ended_at' => '2026-12-31',
                'is_current' => true,

                'source_name' => 'Governo do Estado do Ceará',
                'source_url' => 'https://www.ce.gov.br/o-governo/',

                'external_id' => null,

                'raw_data' => [
                    'sources' => [
                        [
                            'name' => 'Governo do Estado do Ceará',
                            'url' => 'https://www.ce.gov.br/o-governo/',
                        ],
                        [
                            'name' => 'Governo do Estado do Ceará - Vice-Governadoria',
                            'url' => 'https://www.ce.gov.br/vicegov/estrutura-organizacional/',
                        ],
                    ],
                ],
            ],

                        /*
            |--------------------------------------------------------------------------
            | PRESIDENTE DA REPÚBLICA
            |--------------------------------------------------------------------------
            */
            [
                'name' => 'Luiz Inácio Lula da Silva',
                'display_name' => 'Lula',

                'office' => 'president',
                'level' => 'federal',
                'state' => null,

                'party_acronym' => 'PT',
                'party_name' => 'Partido dos Trabalhadores',

                'birth_date' => '1945-10-27',
                'birth_place' => 'Garanhuns, PE',
                'occupation' => 'Presidente da República',

                'education' => 'Ensino primário. Formou-se como torneiro mecânico em curso profissionalizante do Serviço Nacional de Aprendizagem Industrial (SENAI).',

                'biography' => 'Luiz Inácio Lula da Silva nasceu em 27 de outubro de 1945, em Garanhuns, Pernambuco. Ainda criança, migrou com a família para São Paulo. Aos 14 anos, iniciou sua formação profissional no SENAI como torneiro mecânico. Foi metalúrgico, dirigente sindical e participou da fundação do Partido dos Trabalhadores. Foi deputado federal constituinte e exerceu a Presidência da República entre 2003 e 2010. Em 2022, foi eleito novamente presidente, iniciando o terceiro mandato em 1º de janeiro de 2023.',

                'photo_path' => null,

                'started_at' => '2023-01-01',
                'ended_at' => '2026-12-31',
                'is_current' => true,

                'source_name' => 'Presidência da República / Arquivo Nacional',
                'source_url' => 'https://presidentes.an.gov.br/index.php/centro-de-referencia-de-acervos-presidenciais/assuntos/biografias/204-luiz-inacio-lula-da-silva',

                'external_id' => null,

                'raw_data' => [
                    'sources' => [
                        [
                            'name' => 'Arquivo Nacional',
                            'url' => 'https://presidentes.an.gov.br/index.php/centro-de-referencia-de-acervos-presidenciais/assuntos/biografias/204-luiz-inacio-lula-da-silva',
                        ],
                        [
                            'name' => 'Câmara dos Deputados',
                            'url' => 'https://www.camara.leg.br/deputados/139289/biografia',
                        ],
                        [
                            'name' => 'Tribunal Superior Eleitoral',
                            'url' => 'https://www.tse.jus.br/comunicacao/noticias/2022/Outubro/lula-e-eleito-novamente-presidente-da-republica-do-brasil',
                        ],
                    ],
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | VICE-PRESIDENTE DA REPÚBLICA
            |--------------------------------------------------------------------------
            */
            [
                'name' => 'Geraldo José Rodrigues Alckmin Filho',
                'display_name' => 'Geraldo Alckmin',

                'office' => 'vice_president',
                'level' => 'federal',
                'state' => null,

                'party_acronym' => 'PSB',
                'party_name' => 'Partido Socialista Brasileiro',

                'birth_date' => '1952-11-07',
                'birth_place' => 'Pindamonhangaba, SP',
                'occupation' => 'Vice-Presidente da República',

                'education' => 'Graduado em Medicina pela Faculdade de Medicina de Taubaté, com especialização em Anestesiologia.',

                'biography' => 'Geraldo José Rodrigues Alckmin Filho nasceu em 7 de novembro de 1952, em Pindamonhangaba, São Paulo. Formou-se em Medicina e especializou-se em Anestesiologia. Foi vereador, prefeito, deputado estadual, deputado federal, vice-governador e governador de São Paulo. Em 2022, foi eleito vice-presidente da República na chapa de Luiz Inácio Lula da Silva, iniciando o mandato em 1º de janeiro de 2023.',

                'photo_path' => null,

                'started_at' => '2023-01-01',
                'ended_at' => '2026-12-31',
                'is_current' => true,

                'source_name' => 'Vice-Presidência da República',
                'source_url' => 'https://www.gov.br/planalto/pt-br/vice-presidencia',

                'external_id' => null,

                'raw_data' => [
                    'sources' => [
                        [
                            'name' => 'Presidência da República - Vice-Presidência',
                            'url' => 'https://www.gov.br/planalto/pt-br/vice-presidencia',
                        ],
                        [
                            'name' => 'Câmara dos Deputados',
                            'url' => 'https://www.camara.leg.br/deputados/65480/biografia',
                        ],
                        [
                            'name' => 'Tribunal Superior Eleitoral',
                            'url' => 'https://www.tse.jus.br/comunicacao/noticias/2022/Dezembro/tse-proclama-eleitos-presidente-e-vice-presidente-da-republica-luiz-inacio-lula-da-silva-e-geraldo-alckmin',
                        ],
                    ],
                ],
            ],
        ];

        foreach ($executives as $executive) {
            Executive::updateOrCreate(
                [
                    'name' => $executive['name'],
                    'office' => $executive['office'],
                ],
                $executive
            );
        }
    }
}