<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE topics
            DROP CONSTRAINT topics_chamber_check
        ");

        DB::statement("
            ALTER TABLE topics
            ADD CONSTRAINT topics_chamber_check
            CHECK (chamber IN ('lower_house', 'senate', 'votus'))
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE topics
            DROP CONSTRAINT topics_chamber_check
        ");

        DB::statement("
            ALTER TABLE topics
            ADD CONSTRAINT topics_chamber_check
            CHECK (chamber IN ('lower_house', 'senate'))
        ");
    }
};