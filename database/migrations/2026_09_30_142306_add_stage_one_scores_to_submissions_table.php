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
        Schema::table('submissions', function (Blueprint $table) {
            $table->decimal('stage1_raw_score', 7, 4)->nullable()->after('notified_at');
            $table->decimal('stage1_score', 7, 4)->nullable()->after('stage1_raw_score');
            $table->unsignedSmallInteger('stage1_rank')->nullable()->after('stage1_score');
            $table->unsignedTinyInteger('stage1_judges_submitted')->nullable()->after('stage1_rank');
            $table->unsignedTinyInteger('stage1_judges_assigned')->nullable()->after('stage1_judges_submitted');
            $table->timestamp('stage1_calculated_at')->nullable()->after('stage1_judges_assigned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn([
                'stage1_raw_score',
                'stage1_score',
                'stage1_rank',
                'stage1_judges_submitted',
                'stage1_judges_assigned',
                'stage1_calculated_at',
            ]);
        });
    }
};
