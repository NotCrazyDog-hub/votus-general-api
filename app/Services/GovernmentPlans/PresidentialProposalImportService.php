<?php

namespace App\Services\GovernmentPlans;

use App\Models\Candidate;
use App\Models\GovernmentPlanComparison;
use App\Models\GovernmentPlanProposal;
use App\Models\GovernmentPlanTheme;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PresidentialProposalImportService
{
    private const CHUNK_LENGTH = 11000;

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

        $analysis = [];
        foreach ($externalIds as $externalId) {
            $source = $sources[(string) $externalId] ?? null;
            if (!is_array($source)) {
                throw new RuntimeException("Não há site oficial configurado para o candidato {$externalId}.");
            }

            $candidate = $candidates[(string) $externalId];
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

    public function extractProposals(string $candidateName, string $text): array
    {
        $found = [];
        foreach ($this->splitText($text) as $part) {
            foreach (($this->groq->extract($candidateName, $part)['proposals'] ?? []) as $proposal) {
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

    private function persist(array $analysis, array $comparisonRows): array
    {
        return DB::transaction(function () use ($analysis, $comparisonRows) {
            $candidateIds = array_keys($analysis);
            GovernmentPlanComparison::query()
                ->whereIn('candidate_one_id', $candidateIds)
                ->whereIn('candidate_two_id', $candidateIds)
                ->delete();
            GovernmentPlanProposal::whereIn('candidate_id', $candidateIds)->delete();

            $themes = [];
            $proposalCount = 0;
            foreach ($analysis as $candidateId => $data) {
                foreach ($data['proposals'] as $proposal) {
                    $slug = Str::slug($proposal['theme']);
                    if ($slug === '') {
                        $slug = 'tema-' . substr($proposal['fingerprint'], 0, 12);
                    }
                    $theme = $themes[$slug] ??= GovernmentPlanTheme::firstOrCreate(
                        ['slug' => $slug],
                        ['name' => $proposal['theme'], 'position' => 0],
                    );

                    GovernmentPlanProposal::create([
                        'candidate_id' => $candidateId,
                        'government_plan_theme_id' => $theme->id,
                        'title' => $proposal['title'],
                        'description' => $proposal['description'],
                        'source_excerpt' => $proposal['source_excerpt'],
                        'source_reference' => $proposal['source_reference'],
                        'source_url' => $proposal['source_url'],
                        'fingerprint' => $proposal['fingerprint'],
                    ]);
                    $proposalCount++;
                }
            }

            foreach ($comparisonRows as $comparison) {
                $theme = $themes[$comparison['theme_slug']] ?? GovernmentPlanTheme::where('slug', $comparison['theme_slug'])->first();
                if (!$theme) {
                    continue;
                }
                GovernmentPlanComparison::create([
                    'government_plan_theme_id' => $theme->id,
                    'candidate_one_id' => $comparison['candidate_one_id'],
                    'candidate_two_id' => $comparison['candidate_two_id'],
                    'summary' => $comparison['summary'],
                    'similarities' => $comparison['similarities'],
                    'differences' => $comparison['differences'],
                    'candidate_summaries' => $comparison['candidate_summaries'],
                ]);
            }

            return [
                'candidates' => count($analysis),
                'proposals' => $proposalCount,
                'themes' => GovernmentPlanTheme::has('proposals')->count(),
                'comparisons' => count($comparisonRows),
            ];
        });
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
