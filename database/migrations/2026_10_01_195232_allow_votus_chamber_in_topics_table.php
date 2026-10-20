
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE topics DROP CONSTRAINT IF EXISTS topics_chamber_check'
            );
        } elseif (DB::getDriverName() === 'mysql') {
            $exists = DB::selectOne("
                SELECT COUNT(*) AS total
                FROM information_schema.table_constraints
                WHERE constraint_schema = DATABASE()
                  AND table_name = 'topics'
                  AND constraint_name = 'topics_chamber_check'
            ");

            if ($exists->total > 0) {
                DB::statement(
                    'ALTER TABLE topics DROP CHECK topics_chamber_check'
                );
            }
        }

        DB::statement("
            ALTER TABLE topics
            ADD CONSTRAINT topics_chamber_check
            CHECK (chamber IN ('lower_house', 'senate', 'votus'))
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE topics DROP CONSTRAINT IF EXISTS topics_chamber_check'
            );
        } elseif (DB::getDriverName() === 'mysql') {
            $exists = DB::selectOne("
                SELECT COUNT(*) AS total
                FROM information_schema.table_constraints
                WHERE constraint_schema = DATABASE()
                  AND table_name = 'topics'
                  AND constraint_name = 'topics_chamber_check'
            ");

            if ($exists->total > 0) {
                DB::statement(
                    'ALTER TABLE topics DROP CHECK topics_chamber_check'
                );
            }
        }

        DB::statement("
            ALTER TABLE topics
            ADD CONSTRAINT topics_chamber_check
            CHECK (chamber IN ('lower_house', 'senate'))
        ");
    }
};