<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legislature_periods', function (Blueprint $table) {
            $table->dropUnique('legislature_periods_legislature_number_unique');

            $table->unique(
                ['legislature_number', 'chamber'],
                'legislature_periods_legislature_number_chamber_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('legislature_periods', function (Blueprint $table) {
            $table->dropUnique(
                'legislature_periods_legislature_number_chamber_unique'
            );

            $table->unique(
                'legislature_number',
                'legislature_periods_legislature_number_unique'
            );
        });
    }
};