<?php

namespace App\Domains\GovernmentPlans\Services;

use App\Domains\Elections\Models\Candidate;
use App\Domains\GovernmentPlans\Models\GovernmentPlanComparison;
use App\Domains\GovernmentPlans\Models\GovernmentPlanProposal;
use App\Domains\GovernmentPlans\Models\GovernmentPlanTheme;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PresidentialProposalImportService
{
    private const CHUNK_LENGTH = 11000;

    /** @var string[] */
    private array $knownThemes = [];

    public function __construct(
        private readonly GovernmentPlanSiteService $sites,
        private readonly GroqGovernmentPlanService $groq,
    ) {
    }

    public function import(array $externalIds = []): array
    {
        $sources = config('services.government_plans.sites', []);
        $externalIds = $externalIds === [] ? array_keys($sources) : $externalIds;
        $externalIds = array_values(array_unique(array_map('strval', $externalIds)));

        if (count($externalIds) < 2) {
            throw new RuntimeException('Informe ao menos dois candidatos para gerar uma comparação.');
        }

        $candidates = Candidate::query()
            ->whereIn('external_id', $externalIds)
            ->whereNull('running_mate_of_id')
            ->get()
            ->keyBy(fn (Candidate $candidate) => (string) $candidate->external_id);

        $missing = array_diff(array_map('strval', $externalIds), $candidates->keys()->all());
        if ($missing !== []) {
            throw new RuntimeException('Candidato(s) não encontrado(s): ' . implode(', ', $missing));
        }

        // Os temas já existentes e os que forem surgindo seguem para a IA,
        // para os candidatos caírem nos mesmos temas (ver
        // GroqGovernmentPlanService::extract).
        $this->knownThemes = GovernmentPlanTheme::query()->orderBy('name')->pluck('name')->all();

        $analysis = [];
        foreach ($externalIds as $externalId) {
            $source = $sources[(string) $externalId] ?? null;
            if (!is_array($source)) {
                throw new RuntimeException("Não há site oficial configurado para o candidato {$externalId}.");
            }

            $candidate = $candidates[(string) $externalId];

            // Fonte copiada literalmente do site (import:lula-site-proposals):
            // não passa pela IA nem é tocada aqui — só entra na comparação.
            if (!empty($source['literal'])) {
                $analysis[$candidate->id] = [
                    'candidate' => $candidate,
                    'literal' => true,
                    'proposals' => $this->storedProposals($candidate),
                ];
                continue;
            }

            $proposals = [];
            foreach ($this->sites->fetchPages($source) as $page) {
                foreach ($this->extractProposals($candidate->ballot_name, $page['text']) as $proposal) {
                    $proposal['source_url'] = $page['url'];
                    $proposals[$proposal['fingerprint']] = $proposal;
                }
            }
            $analysis[$candidate->id] = [
                'candidate' => $candidate,
                'proposals' => array_values($proposals),
            ];
        }

        $comparisons = $this->generateComparisons($analysis);

        return $this->persist($analysis, $comparisons);
    }

    /**
     * Só regenera os resumos comparativos (IA) a partir das propostas que já
     * estão no banco — não busca site, não extrai propostas, não mexe nelas.
     * Usado depois de import:lula-site-proposals.
     */
    public function compareStored(array $externalIds = []): array
    {
        $externalIds = $externalIds === [] ? array_keys(config('services.government_plans.sites', [])) : $externalIds;
        $candidates = Candidate::query()
            ->whereIn('external_id', array_map('strval', $externalIds))
            ->whereNull('running_mate_of_id')
            ->get();

        if ($candidates->count() < 2) {
            throw new RuntimeException('Informe ao menos dois candidatos para gerar uma comparação.');
        }

        $analysis = [];
        foreach ($candidates as $candidate) {
            $analysis[$candidate->id] = [
                'candidate' => $candidate,
                'literal' => true, // nada de propostas é regravado aqui
                'proposals' => $this->storedProposals($candidate),
            ];
        }

        return $this->persist($analysis, $this->generateComparisons($analysis));
    }

    /**
     * Propostas em uso de um candidato, no formato da análise.
     */
    private function storedProposals(Candidate $candidate): array
    {
        return GovernmentPlanProposal::query()
            ->active()
            ->with('theme')
            ->where('candidate_id', $candidate->id)
            ->orderBy('id')
            ->get()
            ->map(fn (GovernmentPlanProposal $proposal) => [
                'theme' => $proposal->theme->name,
                'title' => $proposal->title,
                'description' => $proposal->description,
                'source_excerpt' => $proposal->source_excerpt,
                'source_reference' => $proposal->source_reference,
                'source_url' => $proposal->source_url,
                'fingerprint' => $proposal->fingerprint,
            ])
            ->all();
    }

    public function extractProposals(string $candidateName, string $text): array
    {
        $found = [];
        foreach ($this->splitText($text) as $part) {
            foreach (($this->groq->extract($candidateName, $part, $this->knownThemes)['proposals'] ?? []) as $proposal) {
                if (!is_array($proposal) || !is_string($proposal['theme'] ?? null)
                    || !is_string($proposal['title'] ?? null) || !is_string($proposal['description'] ?? null)) {
                    continue;
                }

                $theme = trim($proposal['theme']);
                $title = trim($proposal['title']);
                $description = trim($proposal['description']);
                if ($theme === '' || $title === '' || $description === '') {
                    continue;
                }

                if (!in_array($theme, $this->knownThemes, true)) {
                    $this->knownThemes[] = $theme;
                }

                $fingerprint = hash('sha256', Str::lower($theme . '|' . $title . '|' . $description));
                $excerpt = $this->nullableString($proposal['source_excerpt'] ?? null);
                if ($excerpt !== null && !$this->containsNormalized($part, $excerpt)) {
                    $excerpt = null;
                }

                $found[$fingerprint] = [
                    'theme' => $theme,
                    'title' => $title,
                    'description' => $description,
                    'source_excerpt' => $excerpt,
                    'source_reference' => null,
                    'fingerprint' => $fingerprint,
                ];
            }
        }

        return array_values($found);
    }

    private function generateComparisons(array $analysis): array
    {
        $themes = [];
        foreach ($analysis as $candidateId => $data) {
            foreach ($data['proposals'] as $proposal) {
                $slug = Str::slug($proposal['theme']) ?: 'tema-' . substr($proposal['fingerprint'], 0, 12);
                $themes[$slug]['name'] ??= $proposal['theme'];
                $themes[$slug]['proposals'][$candidateId][] = [
                    'title' => $proposal['title'],
                    'description' => $proposal['description'],
                ];
            }
        }

        $comparisons = [];
        $candidateIds = array_keys($analysis);
        sort($candidateIds);
        foreach ($themes as $slug => $data) {
            for ($first = 0; $first < count($candidateIds) - 1; $first++) {
                for ($second = $first + 1; $second < count($candidateIds); $second++) {
                    $firstCandidate = $analysis[$candidateIds[$first]]['candidate'];
                    $secondCandidate = $analysis[$candidateIds[$second]]['candidate'];

                    // Só compara tema em que OS DOIS têm proposta extraída. Com
                    // proposta de um lado só, o texto comparativo acabava
                    // dizendo "o outro não menciona o tema" — conclusão que a
                    // lista extraída não sustenta (o plano pode tratar do
                    // assunto e a extração não ter pego).
                    if (empty($data['proposals'][$candidateIds[$first]]) || empty($data['proposals'][$candidateIds[$second]])) {
                        continue;
                    }

                    $pair = [
                        $firstCandidate->ballot_name => $data['proposals'][$candidateIds[$first]] ?? [],
                        $secondCandidate->ballot_name => $data['proposals'][$candidateIds[$second]] ?? [],
                    ];
                    $result = $this->groq->compare($data['name'], $pair);
                    if (!is_string($result['summary'] ?? null) || trim($result['summary']) === '') {
                        throw new RuntimeException("A comparação do tema {$data['name']} retornou formato inválido.");
                    }

                    $comparisons[] = [
                        'theme_slug' => $slug,
                        'candidate_one_id' => $candidateIds[$first],
                        'candidate_two_id' => $candidateIds[$second],
                        'summary' => trim($result['summary']),
                        'similarities' => array_values(array_filter((array) ($result['similarities'] ?? []), 'is_string')),
                        'differences' => array_values(array_filter((array) ($result['differences'] ?? []), 'is_string')),
                        'candidate_summaries' => is_array($result['candidate_summaries'] ?? null) ? $result['candidate_summaries'] : [],
                    ];
                }
            }
        }

        return $comparisons;
    }

    /**
     * Grava a análise SEM APAGAR NADA do banco:
     *  - proposta nova ou reencontrada: criada/atualizada pela chave
     *    (candidato, fingerprint) e volta a valer (sem "superseded_at");
     *  - proposta que deixou de aparecer: marcada em metadata.superseded_at
     *    (some da API, continua no banco, reversível);
     *  - comparações: atualizadas no lugar pela chave (tema, par de candidatos).
     * Candidatos com fonte literal (analysis[...]['literal']) não têm as
     * propostas regravadas — só entram nas comparações.
     */
    private function persist(array $analysis, array $comparisonRows): array
    {
        return DB::transaction(function () use ($analysis, $comparisonRows) {
            $now = now()->toIso8601String();
            $themes = [];
            $proposalCount = 0;

            foreach ($analysis as $candidateId => $data) {
                if (!empty($data['literal'])) {
                    continue;
                }

                $keptIds = [];
                foreach ($data['proposals'] as $proposal) {
                    $theme = $this->themeFor($proposal['theme'], $proposal['fingerprint'], $themes);

                    $row = GovernmentPlanProposal::updateOrCreate(
                        ['candidate_id' => $candidateId, 'fingerprint' => $proposal['fingerprint']],
                        [
                            'government_plan_theme_id' => $theme->id,
                            'title' => $proposal['title'],
                            'description' => $proposal['description'],
                            'source_excerpt' => $proposal['source_excerpt'],
                            'source_reference' => $proposal['source_reference'],
                            'source_url' => $proposal['source_url'],
                            'metadata' => ['extraction' => 'ai'],
                        ],
                    );
                    $keptIds[] = $row->id;
                    $proposalCount++;
                }

                GovernmentPlanProposal::query()
                    ->active()
                    ->where('candidate_id', $candidateId)
                    ->whereNotIn('id', $keptIds)
                    ->get()
                    ->each(fn (GovernmentPlanProposal $old) => $old->update([
                        'metadata' => array_merge($old->metadata ?? [], [
                            'superseded_at' => $now,
                            'superseded_by' => 'import:presidential-government-plans',
                        ]),
                    ]));
            }

            foreach ($comparisonRows as $comparison) {
                $theme = $themes[$comparison['theme_slug']] ?? GovernmentPlanTheme::where('slug', $comparison['theme_slug'])->first();
                if (!$theme) {
                    continue;
                }

                GovernmentPlanComparison::updateOrCreate(
                    [
                        'government_plan_theme_id' => $theme->id,
                        'candidate_one_id' => $comparison['candidate_one_id'],
                        'candidate_two_id' => $comparison['candidate_two_id'],
                    ],
                    [
                        'summary' => $comparison['summary'],
                        'similarities' => $comparison['similarities'],
                        'differences' => $comparison['differences'],
                        'candidate_summaries' => $comparison['candidate_summaries'],
                    ],
                );
            }

            return [
                'candidates' => count($analysis),
                'proposals' => $proposalCount,
                'themes' => GovernmentPlanTheme::whereHas('proposals', fn ($query) => $query->active())->count(),
                'comparisons' => count($comparisonRows),
            ];
        });
    }

    private function themeFor(string $name, string $fingerprint, array &$themes): GovernmentPlanTheme
    {
        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = 'tema-' . substr($fingerprint, 0, 12);
        }

        return $themes[$slug] ??= GovernmentPlanTheme::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'position' => 0],
        );
    }

    private function splitText(string $text): array
    {
        $parts = [];
        while (mb_strlen($text) > self::CHUNK_LENGTH) {
            $slice = mb_substr($text, 0, self::CHUNK_LENGTH);
            $break = max((int) mb_strrpos($slice, "\n"), (int) mb_strrpos($slice, '.'));
            $break = $break > 1000 ? $break + 1 : self::CHUNK_LENGTH;
            $parts[] = trim(mb_substr($text, 0, $break));
            $text = mb_substr($text, $break);
        }
        if (trim($text) !== '') {
            $parts[] = trim($text);
        }

        return $parts;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function containsNormalized(string $document, string $excerpt): bool
    {
        $normalize = static fn (string $value): string => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));

        return str_contains($normalize($document), $normalize($excerpt));
    }
}
