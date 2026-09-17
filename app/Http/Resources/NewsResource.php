<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewsResource extends JsonResource
{
    // O frontend consome os campos de notícia direto na raiz do JSON (sem
    // wrapper "data"), tanto no show individual quanto em cada item da
    // listagem paginada — precisa desabilitar o wrapping padrão do
    // JsonResource pra não quebrar esse contrato já existente.
    public static $wrap = null;

    /**
     * Whitelist explícita dos campos públicos — nunca expõe status_resumo,
     * erro_resumo, tentativas_resumo, conteudo_original (HTML bruto) nem
     * fonte_id/link_normalizado, que são detalhes internos do pipeline de
     * coleta/resumo sem utilidade (e com risco de vazar informação) pro
     * consumidor público da API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'original_summary' => $this->original_summary,
            'ai_summary' => $this->ai_summary,
            'url' => $this->url,
            'source' => $this->source,
            'category' => $this->category,
            'published_at' => $this->published_at,
            'imported_at' => $this->imported_at,
            'relevance_score' => $this->relevance_score,
            'keywords' => $this->keywords,
            'published' => $this->published,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'image_url' => $this->image_url,
            'site_logo_url' => $this->site_logo_url,
        ];
    }
}
