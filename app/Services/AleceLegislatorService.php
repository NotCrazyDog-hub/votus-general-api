<?php

namespace App\Services;

use App\Models\Legislator;
use App\Models\LegislatorHistory;
use App\Services\Scrapers\AleceLegislatorScraper;
use Illuminate\Support\Facades\DB;
use Throwable;

class AleceLegislatorService
{
    public function __construct(
        protected AleceLegislatorScraper $scraper
    ) {
    }

    /**
     * Importa os parlamentares atuais da ALECE.
     */
    public function sync(?callable $onProgress = null): int
    {
        $legislators = $this->scraper->getLegislatorUrls();

        $total = count($legislators);
        $count = 0;

        foreach ($legislators as $index => $legislator) {
            $error = null;

            $url = $legislator['url'];

            try {
                $data = $this->scraper->scrape(
                    $url,
                    $legislator
                );

                DB::transaction(function () use ($data) {
                    $this->saveLegislator($data);
                });

                $count++;
            } catch (Throwable $e) {
                report($e);

                $error = $e->getMessage();
            }

            if ($onProgress) {
                $onProgress(
                    $index + 1,
                    $total,
                    $count,
                    $url,
                    $error
                );
            }
        }

        return $count;
    }

    /**
     * Salva ou atualiza um parlamentar.
     */
    public function saveLegislator(array $data): Legislator
    {
        $legislator = Legislator::updateOrCreate(
            [
                'source' => $data['source'],
                'source_slug' => $data['source_slug'],
            ],
            [
                'external_id' => $data['external_id'] ?? null,
                'chamber' => 'state_house',
                'civil_name' => $data['name'] ?? null,
                'parliamentary_name' => $data['parliamentary_name'] ?? null,
                'photo_url' => $data['photo_url'] ?? null,
                'party' => $data['party'] ?? null,

                'state' => $data['state'] ?? 'CE',
                'legislature' => $data['legislature'] ?? null,
                'status' => $data['status'] ?? null,
                'electoral_status' => $data['electoral_status'] ?? null,

                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'official_website' => $data['website'] ?? null,
                'social_media' => $data['social_media'] ?? null,

                'raw_data' => $data,
            ]
        );

        /*
        * Mantemos esses campos explícitos porque
        * anteriormente o updateOrCreate não estava
        * persistindo corretamente a identidade da fonte.
        */
        $legislator->source = $data['source'];
        $legislator->source_slug = $data['source_slug'];
        $legislator->source_url = $data['source_url'] ?? null;

        $legislator->save();

        return $legislator;
    }

    /**
     * Salva o histórico parlamentar quando houver informações históricas.
     */
    public function saveHistory(
        Legislator $legislator,
        array $data
    ): ?LegislatorHistory {
        if (empty($data['history'])) {
            return null;
        }

        $history = $data['history'];

        return LegislatorHistory::updateOrCreate(
            [
                'legislator_id' => $legislator->id,
                'legislature' => $history['legislature'] ?? null,
                'start_date' => $history['start_date'] ?? null,
            ],
            [
                'end_date' => $history['end_date'] ?? null,
                'party' => $history['party'] ?? null,
                'office' => $history['office'] ?? null,
                'status' => $history['status'] ?? null,
                'raw_data' => $history,
            ]
        );
    }

    /**
     * Importa um parlamentar individualmente.
     */
    public function syncOne(string $url): Legislator
    {
        $data = $this->scraper->scrape($url);

        return DB::transaction(function () use ($data) {
            $legislator = $this->saveLegislator($data);

            $this->saveHistory($legislator, $data);

            return $legislator;
        });
    }
}