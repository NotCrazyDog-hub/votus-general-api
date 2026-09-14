<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function fonte()
    {
        return $this->belongsTo(Fonte::class, 'fonte_id');
    }
}