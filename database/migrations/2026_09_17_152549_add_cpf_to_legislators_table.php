<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legislators', function (Blueprint $table) {
            $table->string('cpf', 11)->nullable()->after('civil_name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('legislators', function (Blueprint $table) {
            $table->dropColumn('cpf');
        });
    }
};