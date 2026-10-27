
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('legislators', 'source')) {
            Schema::table('legislators', function (Blueprint $table) {
                $table->string('source')->nullable();
            });
        }

        if (!Schema::hasColumn('legislators', 'source_slug')) {
            Schema::table('legislators', function (Blueprint $table) {
                $table->string('source_slug')->nullable();
            });
        }

        if (!Schema::hasColumn('legislators', 'source_url')) {
            Schema::table('legislators', function (Blueprint $table) {
                $table->string('source_url', 2048)->nullable();
            });
        }

        $driver = DB::connection()->getDriverName();

        if (Schema::hasColumn('legislators', 'external_id')) {
            if ($driver === 'pgsql') {
                DB::statement(
                    'ALTER TABLE legislators ALTER COLUMN external_id DROP NOT NULL'
                );
            } elseif ($driver === 'mysql') {
                $column = DB::selectOne(
                    "SHOW COLUMNS FROM legislators LIKE 'external_id'"
                );

                if ($column && $column->Null === 'NO') {
                    $type = $column->Type;

                    DB::statement(
                        "ALTER TABLE legislators MODIFY external_id {$type} NULL"
                    );
                }
            }
        }
    }

    public function down(): void
    {
        // Mantém as alterações para evitar remover colunas
        // ou restrições que podem já existir em produção.
    }
};
