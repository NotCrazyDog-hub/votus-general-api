<?php

namespace App\Domains\News\Services;

use App\Domains\News\Models\News;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Escolhe a notícia principal (destaque) do painel.
 *
 * Antes o front escolhia "a de maior relevância entre TODAS as publicadas":
 * como a nota da IA vai de 0 a 10 e várias antigas tinham 9, o destaque ficava
 * preso na mesma notícia por dias, mesmo com notícias novas chegando.
 *
 * Regra agora (1 a 2 destaques por dia):
 * - candidatas: publicadas, com imagem utilizável, das últimas 48h;
 * - se não houver nenhuma recente (ex.: ciclo parado), cai para as 20 mais
 *   recentes, para nunca ficar sem destaque — mas ainda assim só notícia real;
 * - ordena por relevância e, no empate, pela mais recente;
 * - alterna entre as 2 melhores a cada período de 12h (manhã/tarde no
 *   horário de Fortaleza), então muda ao longo do dia e muda de novo quando
 *   entram notícias novas.
 */
class FeaturedNewsSelector
{
    private const JANELA_RECENTE_HORAS = 48;
    private const FALLBACK_MAIS_RECENTES = 20;
    private const DESTAQUES_POR_DIA = 2;
    private const FUSO = 'America/Fortaleza';

    /**
     * Escolhe uma notícia recente com imagem utilizável e alterna entre as
     * duas mais relevantes conforme o período do dia. Se não houver notícias
     * recentes, usa as últimas publicadas como fallback.
     */
    public function select(): ?News
    {
        $base = fn () => News::query()->where('published', true)->publiclyDisplayable()->whereNotNull('url');

        // Primeiro considera só notícias recentes e exibíveis; o filtro base
        // também exclui registros sem URL ou bloqueados pelo validador público.
        $candidates = $base()
            ->where('published_at', '>=', now()->subHours(self::JANELA_RECENTE_HORAS))
            ->where('published_at', '<=', now()->addHour())
            ->orderByDesc('relevance_score')
            ->orderByDesc('published_at')
            ->limit(self::DESTAQUES_POR_DIA)
            ->get();

        // Se o ciclo de coleta parou e não há notícias recentes, usa as últimas
        // publicadas para manter um destaque disponível na página.
        if ($candidates->isEmpty()) {
            $candidates = $base()
                ->whereIn('id', $base()->orderByDesc('published_at')->limit(self::FALLBACK_MAIS_RECENTES)->pluck('id'))
                ->orderByDesc('relevance_score')
                ->orderByDesc('published_at')
                ->limit(self::DESTAQUES_POR_DIA)
                ->get();
        }

        if ($candidates->isEmpty()) {
            return null;
        }

        // Alterna entre as duas melhores: uma no período da manhã e outra à
        // tarde, usando o fuso da publicação em vez do fuso do servidor.
        $timeOfDay = now(self::FUSO)->hour < 12 ? 0 : 1;
        $chosen = $candidates[$timeOfDay % $candidates->count()];

        $this->recordIfChanged($chosen);

        return $chosen;
    }

    private function recordIfChanged(News $news): void
    {
        $key = 'noticias:destaque-atual';

        if (Cache::store('file')->get($key) !== $news->id) {
            Cache::store('file')->forever($key, $news->id);
            Log::info("[NEWS] Principal selecionada: #{$news->id} {$news->title}");
        }
    }
}
