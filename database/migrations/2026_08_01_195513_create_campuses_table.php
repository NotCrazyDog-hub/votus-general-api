<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campuses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('university_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name')->nullable();

            $table->string('ibge_city_code', 7);
            $table->string('city');
            $table->string('normalized_city');

            $table->string('state', 2);
            $table->string('region')->nullable();

            /*
             * Serão preenchidos na versão 2.
             */
            $table->string('address')->nullable();
            $table->string('postal_code')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->timestamps();

            $table->unique(
                ['university_id', 'ibge_city_code'],
                'university_city_unique'
            );

            $table->index(
                ['state', 'normalized_city'],
                'campuses_state_normalized_city_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campuses');
    }
};