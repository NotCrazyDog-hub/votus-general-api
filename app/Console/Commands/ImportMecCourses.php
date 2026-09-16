<?php

namespace App\Console\Commands;

use App\Models\Campus;
use App\Models\University;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImportMecCourses extends Command
{
    protected $signature = 'universities:import-mec
        {file=PDA_Dados_Cursos_Graduacao_Brasil.csv}
        {--state= : Importar apenas uma UF, como CE}
        {--limit= : Limitar a quantidade de registros para teste}';

    protected $description =
        'Importa universidades, locais de oferta e cursos do CSV do MEC';

    private array $universityCache = [];

    private array $campusCache = [];

    public function handle(): int
    {
        $fileName = (string) $this->argument('file');

        $path = storage_path("app/imports/{$fileName}");

        if (! is_file($path)) {
            $this->error("Arquivo não encontrado: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            $this->error('Não foi possível abrir o arquivo CSV.');

            return self::FAILURE;
        }

        try {
            $delimiter = $this->detectDelimiter($handle);

            $headers = $this->readCsvRow(
                $handle,
                $delimiter
            );

            if ($headers === false) {
                throw new RuntimeException(
                    'O arquivo CSV não possui cabeçalho.'
                );
            }

            $headers = array_map(
                fn ($header) => $this->normalizeHeader(
                    (string) $header
                ),
                $headers
            );

            $this->info(
                'Separador identificado: ' .
                ($delimiter === ';' ? 'ponto e vírgula' : 'vírgula')
            );

            $this->line('Colunas encontradas:');
            $this->line(implode(', ', $headers));

            $stateFilter = $this->option('state');

            $stateFilter = $stateFilter !== null
                ? strtoupper(trim((string) $stateFilter))
                : null;

            $limit = $this->option('limit');

            $limit = $limit !== null
                ? max(1, (int) $limit)
                : null;

            $batch = [];

            $processed = 0;
            $ignored = 0;
            $lineNumber = 1;

            while (
                ($values = $this->readCsvRow(
                    $handle,
                    $delimiter
                )) !== false
            ) {
                $lineNumber++;

                if (count($values) !== count($headers)) {
                    $ignored++;

                    $this->warn(
                        "Linha {$lineNumber} ignorada: número de colunas inválido."
                    );

                    continue;
                }

                $row = array_combine($headers, $values);

                if (! is_array($row)) {
                    $ignored++;

                    continue;
                }

                $state = strtoupper(
                    $this->value($row, [
                        'UF',
                        'SIGLA_UF',
                        'UF_CURSO',
                    ]) ?? ''
                );

                if (
                    $stateFilter !== null &&
                    $state !== $stateFilter
                ) {
                    continue;
                }

                $data = $this->mapRow($row);

                if ($data === null) {
                    $ignored++;

                    continue;
                }

                $university = $this->getUniversity($data);

                $campus = $this->getCampus(
                    $university->id,
                    $data
                );

                $batchKey =
                    $campus->id .
                    '|' .
                    $data['mec_course_code'];

                $now = now();

                $batch[$batchKey] = [
                    'campus_id' => $campus->id,

                    'mec_course_code' =>
                        $data['mec_course_code'],

                    'name' => $data['course_name'],

                    'normalized_name' => $this->normalizeText(
                        $data['course_name']
                    ),

                    'degree' => $data['degree'],
                    'area' => $data['area'],
                    'modality' => $data['modality'],
                    'status' => $data['course_status'],

                    'authorized_vacancies' =>
                        $data['authorized_vacancies'],

                    'workload_hours' =>
                        $data['workload_hours'],

                    'source_name' =>
                        'MEC - Cursos de Graduação do Brasil',

                    'source_updated_at' => '2022-12-29',

                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $processed++;

                if (count($batch) >= 500) {
                    $this->saveBatch($batch);

                    $batch = [];
                }

                if ($processed % 1000 === 0) {
                    $this->info(
                        "{$processed} registros processados."
                    );
                }

                if (
                    $limit !== null &&
                    $processed >= $limit
                ) {
                    break;
                }
            }

            if ($batch !== []) {
                $this->saveBatch($batch);
            }

            fclose($handle);

            $this->newLine();
            $this->info('Importação concluída.');

            $this->table(
                ['Resultado', 'Quantidade'],
                [
                    ['Registros processados', $processed],
                    ['Registros ignorados', $ignored],

                    [
                        'Universidades',
                        DB::table('universities')->count(),
                    ],

                    [
                        'Locais de oferta',
                        DB::table('campuses')->count(),
                    ],

                    [
                        'Cursos',
                        DB::table('course_offerings')->count(),
                    ],
                ]
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            report($exception);

            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function mapRow(array $row): ?array
    {
        $mecUniversityCode = $this->toInteger(
            $this->value($row, [
                'CODIGO_IES',
                'CODIGO_DA_IES',
                'CO_IES',
            ])
        );

        $universityName = $this->value($row, [
            'NOME_IES',
            'NOME_DA_IES',
            'NO_IES',
        ]);

        $mecCourseCode = $this->toInteger(
            $this->value($row, [
                'CODIGO_CURSO',
                'CODIGO_DO_CURSO',
                'CO_CURSO',
            ])
        );

        $courseName = $this->value($row, [
            'NOME_CURSO',
            'NOME_DO_CURSO',
            'NO_CURSO',
        ]);

        /*
         * O CSV está trazendo alguns códigos municipais
         * com zeros adicionais, como:
         *
         * 000000002300200
         *
         * O método normalizeCityCode mantém somente
         * os últimos sete dígitos:
         *
         * 2300200
         */
        $cityCode = $this->normalizeCityCode(
            $this->value($row, [
                'CODIGO_MUNICIPIO',
                'CODIGO_DO_MUNICIPIO',
                'CODIGO_MUNICIPIO_IBGE',
                'CO_MUNICIPIO',
            ])
        );

        $city = $this->value($row, [
            'MUNICIPIO',
            'NOME_MUNICIPIO',
            'NO_MUNICIPIO',
        ]);

        $state = strtoupper(
            $this->value($row, [
                'UF',
                'SIGLA_UF',
                'UF_CURSO',
            ]) ?? ''
        );

        if (
            $mecUniversityCode === null ||
            $mecCourseCode === null ||
            $universityName === null ||
            $courseName === null ||
            $cityCode === null ||
            $city === null ||
            strlen($state) !== 2
        ) {
            return null;
        }

        return [
            'mec_university_code' => $mecUniversityCode,

            'university_name' => $universityName,

            'administrative_category' => $this->value(
                $row,
                [
                    'CATEGORIA_ADMINISTRATIVA',
                    'CATEGORIA_IES',
                    'CATEGORIA_DA_IES',
                ]
            ),

            'academic_organization' => $this->value(
                $row,
                [
                    'ORGANIZACAO_ACADEMICA',
                    'ORGANIZACAO_DA_IES',
                ]
            ),

            'mec_course_code' => $mecCourseCode,

            'course_name' => $courseName,

            'degree' => $this->value($row, [
                'GRAU',
                'GRAU_ACADEMICO',
            ]),

            'area' => $this->value($row, [
                'AREA_OCDE_CINE',
                'AREA_OCDE',
                'AREA',
            ]),

            'modality' => $this->value($row, [
                'MODALIDADE',
                'MODALIDADE_DE_ENSINO',
            ]),

            'course_status' => $this->value($row, [
                'SITUACAO_CURSO',
                'SITUACAO_DO_CURSO',
                'SITUACAO',
            ]),

            'authorized_vacancies' => $this->toInteger(
                $this->value($row, [
                    'QT_VAGAS_AUTORIZADAS',
                    'VAGAS_AUTORIZADAS',
                    'QTDE_VAGAS_AUTORIZADAS',
                    'QUANTIDADE_DE_VAGAS_AUTORIZADAS',
                ])
            ),

            'workload_hours' => $this->toInteger(
                $this->value($row, [
                    'CARGA_HORARIA',
                    'CARGA_HORARIA_CURSO',
                    'CARGA_HORARIA_TOTAL',
                ])
            ),

            'ibge_city_code' => $cityCode,

            'city' => $city,

            'state' => $state,

            'region' => $this->value($row, [
                'REGIAO',
                'NOME_REGIAO',
            ]),
        ];
    }

    private function getUniversity(array $data): University
    {
        $code = $data['mec_university_code'];

        if (isset($this->universityCache[$code])) {
            return $this->universityCache[$code];
        }

        $university = University::updateOrCreate(
            [
                'mec_code' => $code,
            ],
            [
                'name' => $data['university_name'],

                'administrative_category' =>
                    $data['administrative_category'],

                'academic_organization' =>
                    $data['academic_organization'],

                'sector' => $this->identifySector(
                    $data['administrative_category']
                ),
            ]
        );

        $this->universityCache[$code] = $university;

        return $university;
    }

    private function getCampus(
        int $universityId,
        array $data
    ): Campus {
        $cacheKey =
            $universityId .
            '|' .
            $data['ibge_city_code'];

        if (isset($this->campusCache[$cacheKey])) {
            return $this->campusCache[$cacheKey];
        }

        $campus = Campus::updateOrCreate(
            [
                'university_id' => $universityId,

                'ibge_city_code' =>
                    $data['ibge_city_code'],
            ],
            [
                'city' => $data['city'],

                'normalized_city' => $this->normalizeText(
                    $data['city']
                ),

                'state' => $data['state'],

                'region' => $data['region'],
            ]
        );

        $this->campusCache[$cacheKey] = $campus;

        return $campus;
    }

    private function saveBatch(array $batch): void
    {
        DB::table('course_offerings')->upsert(
            array_values($batch),
            [
                'campus_id',
                'mec_course_code',
            ],
            [
                'name',
                'normalized_name',
                'degree',
                'area',
                'modality',
                'status',
                'authorized_vacancies',
                'workload_hours',
                'source_name',
                'source_updated_at',
                'updated_at',
            ]
        );
    }

    private function detectDelimiter($handle): string
    {
        $sample = fgets($handle);

        if ($sample === false) {
            throw new RuntimeException(
                'Não foi possível ler o arquivo.'
            );
        }

        rewind($handle);

        $semicolonCount = substr_count($sample, ';');
        $commaCount = substr_count($sample, ',');

        return $semicolonCount >= $commaCount
            ? ';'
            : ',';
    }

    private function readCsvRow(
        $handle,
        string $delimiter
    ): array|false {
        $row = fgetcsv(
            $handle,
            null,
            $delimiter,
            '"',
            ''
        );

        if ($row === false) {
            return false;
        }

        return array_map(
            fn ($value) => $this->toUtf8(
                (string) $value
            ),
            $row
        );
    }

    private function toUtf8(string $value): string
    {
        $value = trim($value);

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return trim(
            mb_convert_encoding(
                $value,
                'UTF-8',
                'Windows-1252'
            )
        );
    }

    private function normalizeHeader(
        string $header
    ): string {
        $header = str_replace(
            "\xEF\xBB\xBF",
            '',
            $header
        );

        return Str::of($header)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }

    private function normalizeText(
        string $value
    ): string {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->squish()
            ->toString();
    }

    private function value(
        array $row,
        array $possibleColumns
    ): ?string {
        foreach ($possibleColumns as $column) {
            $normalizedColumn = $this->normalizeHeader(
                $column
            );

            if (! array_key_exists($normalizedColumn, $row)) {
                continue;
            }

            $value = trim(
                (string) $row[$normalizedColumn]
            );

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function normalizeCityCode(
        ?string $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $numbers = preg_replace(
            '/[^0-9]/',
            '',
            trim($value)
        );

        if (
            $numbers === null ||
            $numbers === ''
        ) {
            return null;
        }

        /*
         * Código IBGE municipal:
         * exatamente sete dígitos.
         */
        $numbers = substr($numbers, -7);

        if (strlen($numbers) < 7) {
            $numbers = str_pad(
                $numbers,
                7,
                '0',
                STR_PAD_LEFT
            );
        }

        return $numbers;
    }

    private function toInteger(
        ?string $value
    ): ?int {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        /*
         * Remove uma eventual parte decimal contendo
         * apenas zeros, como:
         *
         * 12345.0
         * 12345,00
         */
        $value = preg_replace(
            '/[.,]0+$/',
            '',
            $value
        );

        $numbers = preg_replace(
            '/[^0-9]/',
            '',
            $value
        );

        if (
            $numbers === null ||
            $numbers === ''
        ) {
            return null;
        }

        return (int) $numbers;
    }

    private function identifySector(
        ?string $administrativeCategory
    ): ?string {
        if ($administrativeCategory === null) {
            return null;
        }

        $normalized = $this->normalizeText(
            $administrativeCategory
        );

        if (str_contains($normalized, 'publica')) {
            return 'public';
        }

        if (str_contains($normalized, 'privada')) {
            return 'private';
        }

        /*
        * Algumas versões dos dados podem usar nomes
        * como federal, estadual ou municipal.
        */
        if (
            str_contains($normalized, 'federal') ||
            str_contains($normalized, 'estadual') ||
            str_contains($normalized, 'municipal')
        ) {
            return 'public';
        }

        return null;
    }
}