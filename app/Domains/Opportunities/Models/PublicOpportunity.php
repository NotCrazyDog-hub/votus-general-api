<?php

namespace App\Domains\Opportunities\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicOpportunity extends Model
{
    protected $fillable = [
        'source_key',
        'type',
        'title',
        'notice_number',
        'agency',
        'municipality',
        'state',
        'positions',
        'education_levels',
        'vacancies',
        'salary_min',
        'salary_max',
        'registration_start',
        'registration_end',
        'exam_date',
        'fee_min',
        'fee_max',
        'registration_url',
        'summary',
        'review_status',
        'first_seen_at',
        'last_seen_at',
    ];


    protected $appends = [
        'status'
    ];


    /** Define conversões de arrays, valores decimais, datas e timestamps do modelo. */
    protected function casts(): array
    {
        // Campos estruturados ficam como arrays; valores monetários preservam
        // duas casas decimais e datas são expostas como objetos de data.
        return [
            'positions' => 'array',

            'education_levels' => 'array',

            'vacancies' => 'integer',

            'salary_min' => 'decimal:2',

            'salary_max' => 'decimal:2',

            'fee_min' => 'decimal:2',

            'fee_max' => 'decimal:2',

            'registration_start' => 'date',

            'registration_end' => 'date',

            'exam_date' => 'date',

            'first_seen_at' => 'datetime',

            'last_seen_at' => 'datetime',
        ];
    }


    /**
     * Relação um-para-muitos com o modelo `OpportunityPublication`: o registro atual pode estar associado a vários registros desse modelo.
     */
    public function publications(): HasMany
    {
        return $this->hasMany(
            OpportunityPublication::class
        );
    }


    protected function status(): Attribute
    {
        // O status é derivado das datas de inscrição e não precisa ser gravado
        // no banco; o accessor o inclui na serialização por causa de $appends.
        return Attribute::make(
            get: function (): string {

                $today = now()
                    ->startOfDay();


                // Uma inscrição futura tem precedência sobre as demais datas.
                if (
                    $this->registration_start &&
                    $today->lt(
                        $this->registration_start
                    )
                ) {
                    return 'em_breve';
                }


                // Considera o último dia inteiro como aberto, inclusive hoje.
                if ($this->registration_end) {

                    $end =
                        $this->registration_end
                            ->copy()
                            ->endOfDay();


                    if ($today->lte($end)) {
                        return 'aberto';
                    }

                    return 'encerrado';
                }


                // Não existem informações suficientes.
                return 'indefinido';
            }
        );
    }

    /** Usa source_key na resolução automática do model binding das rotas. */
    public function getRouteKeyName(): string
    {
        return 'source_key';
    }
}