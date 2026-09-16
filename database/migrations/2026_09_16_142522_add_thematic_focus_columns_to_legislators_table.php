<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legislators', function (Blueprint $table) {
            $table->decimal('thematic_focus_index', 5, 4)
                ->nullable();

            $table->foreignId('thematic_focus_top_topic_id')
                ->nullable()
                ->constrained('topics')
                ->nullOnDelete();

            $table->decimal('thematic_focus_top_topic_share', 5, 4)
                ->nullable();

            $table->unsignedInteger('thematic_focus_total_bills')
                ->nullable();

            $table->timestamp('thematic_focus_calculated_at')
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('legislators', function (Blueprint $table) {
            $table->dropForeign([
                'thematic_focus_top_topic_id',
            ]);

            $table->dropColumn([
                'thematic_focus_index',
                'thematic_focus_top_topic_id',
                'thematic_focus_top_topic_share',
                'thematic_focus_total_bills',
                'thematic_focus_calculated_at',
            ]);
        });
    }
};
