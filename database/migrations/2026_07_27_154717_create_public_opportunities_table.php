<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_opportunities', function (Blueprint $table) {

            $table->id();

            // Identificação gerada pelo n8n.
            $table->string('source_key', 100)
                ->unique();

            $table->string('type', 60);

            $table->string('title', 500)
                ->nullable();

            $table->string('notice_number', 100)
                ->nullable();

            $table->string('agency', 500)
                ->nullable();

            $table->string('municipality', 200)
                ->nullable()
                ->index();

            $table->string('state', 2)
                ->nullable()
                ->index();


            // Arrays retornados pela IA.
            $table->json('positions')
                ->nullable();

            $table->json('education_levels')
                ->nullable();


            $table->unsignedInteger('vacancies')
                ->nullable();


            $table->decimal('salary_min', 12, 2)
                ->nullable();

            $table->decimal('salary_max', 12, 2)
                ->nullable();


            $table->date('registration_start')
                ->nullable();

            $table->date('registration_end')
                ->nullable();

            $table->date('exam_date')
                ->nullable();


            $table->decimal('fee_min', 10, 2)
                ->nullable();

            $table->decimal('fee_max', 10, 2)
                ->nullable();


            $table->text('registration_url')
                ->nullable();

            $table->text('summary')
                ->nullable();


            // Controle da revisão humana.
            $table->string('review_status', 30)
                ->default('pending')
                ->index();


            $table->timestamp('first_seen_at')
                ->nullable();

            $table->timestamp('last_seen_at')
                ->nullable();


            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_opportunities');
    }
};
