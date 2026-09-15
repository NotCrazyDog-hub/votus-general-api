<?php

namespace App\Services\Metrics;

use App\Models\Committee;
use App\Models\Topic;
use Illuminate\Support\Str;

class CommitteeTopicMatchingService
{
    protected const STOPWORDS = [
        'comissao', 'de', 'da', 'do', 'das', 'dos', 'e', 'para', 'sobre', 'em',
    ];

    public function generateCandidates(float $minScore = 0.3): array
    {
        $committees = Committee::all();
        $topics = Topic::all();
        $candidates = [];

        foreach ($committees as $committee) {
            $committeeTokens = $this->tokenize($committee->name);

            foreach ($topics as $topic) {
                $topicTokens = $this->tokenize($topic->name);
                $score = $this->jaccardSimilarity($committeeTokens, $topicTokens);

                if ($score >= $minScore) {
                    $candidates[] = [
                        'committee_id' => $committee->id,
                        'committee_name' => $committee->name,
                        'topic_id' => $topic->id,
                        'topic_name' => $topic->name,
                        'score' => round($score, 4),
                    ];
                }
            }
        }

        return collect($candidates)->sortByDesc('score')->values()->all();
    }

    protected function tokenize(string $text): array
    {
        $normalized = Str::of($text)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9\s]/', '')
            ->toString();

        $tokens = collect(explode(' ', $normalized))
            ->filter(fn ($token) => strlen($token) > 2 && !in_array($token, self::STOPWORDS))
            ->values();

        // aplica sinônimos ANTES do stemming, pra não perder o match do dicionário
        $expanded = $tokens->flatMap(function ($token) {
            $synonyms = config("committee_topic_synonyms.{$token}", []);
            return array_merge([$token], $synonyms);
        });

        // stemming em tudo (originais + sinônimos), pra normalizar variações morfológicas
        return $expanded
            ->flatMap(fn ($phrase) => explode(' ', $phrase)) // sinônimos podem ser frases ("direitos humanos")
            ->map(fn ($word) => $this->stem($word))
            ->unique()
            ->values()
            ->all();
    }

    protected function stem(string $word): string
    {
        $suffixes = [
            'acoes', 'acao',
            'mentos', 'mento',
            'arias', 'aria', 'arios', 'ario',
            'istas', 'ista',
            'ais', 'al',
            'ivas', 'ivo', 'iva',
            'icas', 'ico', 'ica',
            'oes',
            'es',
            's',
        ];

        foreach ($suffixes as $suffix) {
            if (strlen($word) > strlen($suffix) + 3 && str_ends_with($word, $suffix)) {
                return substr($word, 0, -strlen($suffix));
            }
        }

        return $word;
    }

    protected function jaccardSimilarity(array $a, array $b): float
    {
        if (empty($a) || empty($b)) {
            return 0.0;
        }

        $intersection = count(array_intersect($a, $b));
        $union = count(array_unique(array_merge($a, $b)));

        return $union > 0 ? $intersection / $union : 0.0;
    }
}