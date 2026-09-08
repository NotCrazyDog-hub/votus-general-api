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
        Schema::table('legislators', function (Blueprint $table) {
            $table->unsignedInteger('effectiveness_total_bills')->nullable();
            $table->unsignedInteger('effectiveness_advanced_bills')->nullable();
            $table->decimal('effectiveness_rate', 5, 4)->nullable();
            $table->decimal('effectiveness_wilson_lower', 5, 4)->nullable();
            $table->timestamp('effectiveness_calculated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legislators', function (Blueprint $table) {
            $table->dropColumn([
                'effectiveness_total_bills',
                'effectiveness_advanced_bills',
                'effectiveness_rate',
                'effectiveness_wilson_lower',
                'effectiveness_calculated_at',
            ]);
        });
    }
};
