<?php

namespace App\Domains\GovernmentPlans\Console\Commands;

use App\Domains\Elections\Models\Candidate;
use App\Domains\GovernmentPlans\Models\GovernmentPlanProposal;
use App\Domains\GovernmentPlans\Models\GovernmentPlanTheme;
use App\Domains\GovernmentPlans\Services\LulaSiteProposalParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Troca as propostas do Lula (antes: resumos da IA) pelo texto literal do site
 * dele. NÃO APAGA NADA: as propostas anteriores ficam no banco, marcadas em
 * metadata.superseded_at (somem da API; reversível). Roda só com --force em
 * produção; --dry-run mostra o que faria sem gravar.
 */
class ImportLulaSiteProposals extends Command
{
    private const EXTERNAL_ID = '280002542548';

    protected $signature = 'import:lula-site-proposals
        {--dry-run : mostra o que seria feito, sem gravar}
        {--force : confirma a escrita quando APP_ENV=production}';

    protected $description = 'Importa as propostas do site oficial do Lula (texto literal, sem IA) e retira de uso as anteriores sem apagá-las';

    public function handle(LulaSiteProposalParser $parser): int
    {
        $dry = (bool) $this->option('dry-run');

        if (!$dry && app()->environment('production') && !$this->option('force')) {
            $this->error('Execução bloqueada em produção. Use --dry-run para conferir e --force para gravar.');

            return self::FAILURE;
        }

        $url = config('services.government_plans.sites.' . self::EXTERNAL_ID . '.url');
        $candidate = Candidate::where('external_id', self::EXTERNAL_ID)->whereNull('running_mate_of_id')->first();

        if (!$candidate || !$url) {
            $this->error('Candidato ou URL do site não configurados.');

            return self::FAILURE;
        }

        try {
            $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; VotusBot/1.0; +https://votus-front.vercel.app)'])
                ->timeout(60)->retry(2, 2000)->get($url);

            if ($response->failed()) {
                throw new \RuntimeException("Site respondeu HTTP {$response->status()}");
            }

            $items = $parser->parse($response->body());
        } catch (Throwable $e) {
            $this->error("Não foi possível ler o site: {$e->getMessage()}");

            return self::FAILURE;
        }

        $now = now()->toIso8601String();
        $existing = GovernmentPlanProposal::where('candidate_id', $candidate->id)->get()->keyBy('fingerprint');
        $toCreate = [];
        $fingerprints = [];

        foreach ($items as $item) {
            $fingerprint = hash('sha256', mb_strtolower("{$item['theme_slug']}|{$item['title']}|{$item['text']}"));
            $fingerprints[] = $fingerprint;
            $item['fingerprint'] = $fingerprint;

            if (!isset($existing[$fingerprint])) {
                $toCreate[] = $item;
            }
        }

        $toSupersede = GovernmentPlanProposal::where('candidate_id', $candidate->id)
            ->active()
            ->whereNotIn('fingerprint', $fingerprints)
            ->get();

        $this->info(sprintf(
            '%s: %d propostas lidas do site (%d já gravadas); %d novas a gravar; %d anteriores a retirar de uso (ficam no banco).',
            $url,
            count($items),
            count($items) - count($toCreate),
            count($toCreate),
            $toSupersede->count(),
        ));

        foreach (collect($items)->groupBy('theme_name') as $theme => $group) {
            $this->line(sprintf('   %-18s %d', $theme, $group->count()));
        }

        if ($dry) {
            $this->warn('--dry-run: nada foi gravado.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($items, $candidate, $url, $now, $toSupersede) {
            $themes = [];

            foreach ($items as $item) {
                $theme = $themes[$item['theme_slug']] ??= GovernmentPlanTheme::firstOrCreate(
                    ['slug' => $item['theme_slug']],
                    ['name' => $item['theme_name'], 'position' => 0],
                );

                GovernmentPlanProposal::updateOrCreate(
                    [
                        'candidate_id' => $candidate->id,
                        'fingerprint' => hash('sha256', mb_strtolower("{$item['theme_slug']}|{$item['title']}|{$item['text']}")),
                    ],
                    [
                        'government_plan_theme_id' => $theme->id,
                        'title' => $item['title'],
                        'description' => $item['text'],
                        'source_excerpt' => null,
                        'source_reference' => null,
                        'source_url' => $item['section_id'] !== '' ? "{$url}#{$item['section_id']}" : $url,
                        'metadata' => [
                            'extraction' => 'literal',
                            'site_section' => $item['section'],
                            'site_block' => $item['block'],
                            'imported_at' => $now,
                        ],
                    ],
                );
            }

            foreach ($toSupersede as $old) {
                $old->update(['metadata' => array_merge($old->metadata ?? [], [
                    'superseded_at' => $now,
                    'superseded_by' => 'import:lula-site-proposals',
                ])]);
            }
        });

        $active = GovernmentPlanProposal::where('candidate_id', $candidate->id)->active()->count();
        $total = GovernmentPlanProposal::where('candidate_id', $candidate->id)->count();
        $this->info("Concluído: {$active} propostas em uso; {$total} registros no banco (nenhum apagado).");
        $this->line('Para atualizar os resumos comparativos: php artisan compare:presidential-plans --force');

        return self::SUCCESS;
    }
}
