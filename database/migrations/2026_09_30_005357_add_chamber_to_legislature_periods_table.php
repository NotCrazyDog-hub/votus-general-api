<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legislature_periods', function (Blueprint $table) {
            $table->string('chamber')->nullable()->after('legislature_number');
        });

        DB::table('legislature_periods')->whereNull('chamber')->update(['chamber' => 'lower_house']);
    }

    public function down(): void
    {
        Schema::table('legislature_periods', function (Blueprint $table) {
            $table->dropColumn('chamber');
        });
    }
};