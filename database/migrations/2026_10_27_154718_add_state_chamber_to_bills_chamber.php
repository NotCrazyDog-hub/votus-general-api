<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // No Postgres o enum do Laravel é varchar + CHECK constraint.
            // Basta remover o CHECK; a coluna já é varchar(255).
            DB::statement('ALTER TABLE bills DROP CONSTRAINT IF EXISTS bills_chamber_check');
        } elseif (in_array($driver, ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE bills MODIFY chamber VARCHAR(30) NOT NULL');
        }
    }

    public function down(): void
    {
        // Intencionalmente vazio: restaurar o enum falharia se já existirem
        // linhas com 'state_house'.
    }
};