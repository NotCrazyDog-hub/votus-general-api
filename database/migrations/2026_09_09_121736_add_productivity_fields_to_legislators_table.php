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
            $table->date('mandate_started_at')->nullable();
            $table->decimal('productivity_bills_per_year', 6, 2)->nullable();
            $table->timestamp('productivity_calculated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legislators', function (Blueprint $table) {
            $table->dropColumn([
                'mandate_started_at',
                'productivity_bills_per_year',
                'productivity_calculated_at',
            ]);
        });
    }

};
