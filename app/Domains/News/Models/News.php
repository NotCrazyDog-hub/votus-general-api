<?php

namespace App\Domains\News\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Domains\News\Services\NewsImageValidator;

class News extends Model
{
    use HasFactory;

    protected $table = 'news';

    protected $fillable = [
        'fonte_id',
        'title',
        'original_summary',
        'conteudo_original',
        'ai_summary',
        'status_resumo',
        'erro_resumo',
        'tentativas_resumo',
        'ultima_tentativa_resumo_em',
        'url',
        'link_normalizado',
        'source',
        'category',
        'eixo',
        'published_at',
        'imported_at',
        'relevance_score',
        'keywords',
        'published',
        'image_url',
        'site_logo_url',
    ];

    protected $casts = [
        'keywords' => 'array',
        'published_at' => 'datetime',
        'imported_at' => 'datetime',
        'published' => 'boolean',
        'tentativas_resumo' => 'integer',
        'ultima_tentativa_resumo_em' => 'datetime',
    ];

    public function source()
    {
        return $this->belongsTo(NewsSource::class, 'fonte_id');
    }

    /**
     * Só notícias com imagem utilizável aparecem publicamente. Novas notícias
     * sem imagem já nem são gravadas (NewsImageValidator, antes da
     * persistência); isto cobre os registros antigos — 33 publicadas sem
     * imagem no banco em 25/09 e as que usam arte genérica da fonte — sem
     * apagar nada: elas continuam no banco, só não entram na vitrine.
     */
    public function scopePubliclyDisplayable($query)
    {
        $query->whereNotNull('image_url')->where('image_url', '<>', '');

        // lower() + like: funciona igual no Postgres (produção) e no SQLite
        // (testes) — mesmos trechos que o validador usa antes de gravar.
        foreach (NewsImageValidator::TRECHOS_GENERICOS as $snippet) {
            $query->whereRaw('lower(image_url) not like ?', ['%' . $snippet . '%']);
        }

        return $query;
    }
}