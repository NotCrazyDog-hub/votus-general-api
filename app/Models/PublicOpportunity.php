<?php

namespace App\Models;

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


    protected function casts(): array
    {
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


    public function publications(): HasMany
    {
        return $this->hasMany(
            OpportunityPublication::class
        );
    }


    protected function status(): Attribute
    {
        return Attribute::make(
            get: function (): string {

                $today = now()
                    ->startOfDay();


                // Ainda não começou
                if (
                    $this->registration_start &&
                    $today->lt(
                        $this->registration_start
                    )
                ) {
                    return 'em_breve';
                }


                // Temos data final
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
}