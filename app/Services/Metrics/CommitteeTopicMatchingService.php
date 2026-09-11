<?php

namespace App\Services\Metrics;

use App\Models\Committee;
use App\Models\Topic;
use Illuminate\Support\Str;

class CommitteeTopicMatchingService
{
    protected const STOPWORDS = [
        'comissão', 'de', 'da', 'do', 'das', 'dos', 'e', 'para', 'sobre',
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
            ->ascii() // remove acentos
            ->replaceMatches('/[^a-z0-9\s]/', '')
            ->toString();

        return collect(explode(' ', $normalized))
            ->filter(fn ($token) => strlen($token) > 2 && !in_array($token, self::STOPWORDS))
            ->unique()
            ->values()
            ->all();
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