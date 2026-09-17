<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fonte extends Model
{
    use HasFactory;

    protected $table = 'fontes';

    protected $fillable = [
        'nome',
        'slug',
        'tipo_coleta',
        'url_base',
        'feeds',
        'ativa',
        'offset_minutos',
        'limite_falhas',
        'falhas_consecutivas',
        'ultima_falha_em',
        'ultimo_erro',
        'desativada_em',
        'ultima_coleta_em',
    ];

    protected $casts = [
        'feeds' => 'array',
        'ativa' => 'boolean',
        'offset_minutos' => 'integer',
        'limite_falhas' => 'integer',
        'falhas_consecutivas' => 'integer',
        'ultima_falha_em' => 'datetime',
        'desativada_em' => 'datetime',
        'ultima_coleta_em' => 'datetime',
    ];

    public function news()
    {
        return $this->hasMany(News::class, 'fonte_id');
    }

    public function registrarFalha(string $erro): void
    {
        $this->falhas_consecutivas++;
        $this->ultima_falha_em = now();
        $this->ultimo_erro = str($erro)->limit(500)->toString();

        if ($this->falhas_consecutivas >= $this->limite_falhas) {
            $this->ativa = false;
            $this->desativada_em = now();
        }

        $this->save();
    }

    public function registrarSucesso(): void
    {
        $this->falhas_consecutivas = 0;
        $this->ultima_coleta_em = now();
        $this->save();
    }

    public function reativar(): void
    {
        $this->ativa = true;
        $this->falhas_consecutivas = 0;
        $this->ultimo_erro = null;
        $this->ultima_falha_em = null;
        $this->desativada_em = null;
        $this->save();
    }
}
