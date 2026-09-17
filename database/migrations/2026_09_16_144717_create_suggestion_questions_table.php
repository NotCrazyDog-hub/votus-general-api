<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('suggestion_questions', function (Blueprint $table) {
            $table->id();
            $table->string('text');
            $table->string('type'); // 'choice' | 'text'
            $table->json('options')->nullable(); // só quando type = 'choice'
            $table->boolean('required')->default(true);
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suggestion_questions');
    }
};
