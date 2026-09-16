<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'opportunities',
            function (Blueprint $table) {

                $table->id();


                /*
                |--------------------------------------------------------------------------
                | Origem
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'source',
                    50
                )->default('adzuna');

                $table->string(
                    'external_id',
                    150
                );


                /*
                |--------------------------------------------------------------------------
                | Tipo dentro do Votus
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'opportunity_type',
                    50
                )->default('emprego');


                /*
                |--------------------------------------------------------------------------
                | Informações principais
                |--------------------------------------------------------------------------
                */

                $table->string('title');

                $table->string(
                    'company'
                )->nullable();

                $table->text(
                    'description'
                )->nullable();


                /*
                |--------------------------------------------------------------------------
                | Localização
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'location'
                )->nullable();

                $table->json(
                    'location_area'
                )->nullable();

                $table->decimal(
                    'latitude',
                    10,
                    7
                )->nullable();

                $table->decimal(
                    'longitude',
                    10,
                    7
                )->nullable();


                /*
                |--------------------------------------------------------------------------
                | Categoria e contrato
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'category'
                )->nullable();

                $table->string(
                    'contract_type'
                )->nullable();

                $table->string(
                    'contract_time'
                )->nullable();


                /*
                |--------------------------------------------------------------------------
                | Salário
                |--------------------------------------------------------------------------
                */

                $table->decimal(
                    'salary_min',
                    12,
                    2
                )->nullable();

                $table->decimal(
                    'salary_max',
                    12,
                    2
                )->nullable();


                /*
                |--------------------------------------------------------------------------
                | Fonte
                |--------------------------------------------------------------------------
                */

                $table->text('external_url');


                /*
                |--------------------------------------------------------------------------
                | Datas
                |--------------------------------------------------------------------------
                */

                $table->timestamp(
                    'published_at'
                )->nullable();

                $table->timestamp(
                    'last_seen_at'
                )->nullable();


                /*
                |--------------------------------------------------------------------------
                | Situação
                |--------------------------------------------------------------------------
                */

                $table->boolean(
                    'is_active'
                )->default(true);


                $table->timestamps();


                /*
                |--------------------------------------------------------------------------
                | Índices
                |--------------------------------------------------------------------------
                */

                $table->unique([
                    'source',
                    'external_id',
                ]);

                $table->index(
                    'opportunity_type'
                );

                $table->index(
                    'published_at'
                );

                $table->index(
                    'is_active'
                );
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'opportunities'
        );
    }
};