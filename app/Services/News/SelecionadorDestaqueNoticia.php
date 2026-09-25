<?php

namespace App\Services\News;

use App\Models\News;
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
class SelecionadorDestaqueNoticia
{
    private const JANELA_RECENTE_HORAS = 48;
    private const FALLBACK_MAIS_RECENTES = 20;
    private const DESTAQUES_POR_DIA = 2;
    private const FUSO = 'America/Fortaleza';

    public function selecionar(): ?News
    {
        $base = fn () => News::query()->where('published', true)->comImagemPublicavel()->whereNotNull('url');

        $candidatas = $base()
            ->where('published_at', '>=', now()->subHours(self::JANELA_RECENTE_HORAS))
            ->where('published_at', '<=', now()->addHour())
            ->orderByDesc('relevance_score')
            ->orderByDesc('published_at')
            ->limit(self::DESTAQUES_POR_DIA)
            ->get();

        if ($candidatas->isEmpty()) {
            $candidatas = $base()
                ->whereIn('id', $base()->orderByDesc('published_at')->limit(self::FALLBACK_MAIS_RECENTES)->pluck('id'))
                ->orderByDesc('relevance_score')
                ->orderByDesc('published_at')
                ->limit(self::DESTAQUES_POR_DIA)
                ->get();
        }

        if ($candidatas->isEmpty()) {
            return null;
        }

        $periodoDoDia = now(self::FUSO)->hour < 12 ? 0 : 1;
        $escolhida = $candidatas[$periodoDoDia % $candidatas->count()];

        $this->registrarSeMudou($escolhida);

        return $escolhida;
    }

    private function registrarSeMudou(News $noticia): void
    {
        $chave = 'noticias:destaque-atual';

        if (Cache::store('file')->get($chave) !== $noticia->id) {
            Cache::store('file')->forever($chave, $noticia->id);
            Log::info("[NEWS] Principal selecionada: #{$noticia->id} {$noticia->title}");
        }
    }
}
