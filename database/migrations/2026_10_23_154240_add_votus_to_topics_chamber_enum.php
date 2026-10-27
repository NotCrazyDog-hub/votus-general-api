
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE topics
            MODIFY COLUMN chamber
            ENUM('lower_house', 'senate', 'votus')
            NOT NULL
        ");
    }

    public function down(): void
    {
        // Não remover 'votus' automaticamente,
        // pois podem existir registros usando esse valor.
    }
};
