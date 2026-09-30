<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE bills DROP CONSTRAINT IF EXISTS bills_chamber_check'
        );

        DB::statement(
            "ALTER TABLE bills
             ADD CONSTRAINT bills_chamber_check
             CHECK (chamber IN ('lower_house', 'senate', 'state_house'))"
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE bills DROP CONSTRAINT IF EXISTS bills_chamber_check'
        );

        DB::statement(
            "ALTER TABLE bills
             ADD CONSTRAINT bills_chamber_check
             CHECK (chamber IN ('lower_house', 'senate'))"
        );
    }
};