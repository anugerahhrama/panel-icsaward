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
            $table->decimal('stage2_raw_score', 7, 4)->nullable()->after('stage1_calculated_at');
            $table->decimal('stage2_score', 7, 4)->nullable()->after('stage2_raw_score');
            $table->unsignedSmallInteger('stage2_rank')->nullable()->after('stage2_score');
            $table->unsignedTinyInteger('stage2_judges_submitted')->nullable()->after('stage2_rank');
            $table->unsignedTinyInteger('stage2_judges_assigned')->nullable()->after('stage2_judges_submitted');
            $table->timestamp('stage2_calculated_at')->nullable()->after('stage2_judges_assigned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn([
                'stage2_raw_score',
                'stage2_score',
                'stage2_rank',
                'stage2_judges_submitted',
                'stage2_judges_assigned',
                'stage2_calculated_at',
            ]);
        });
    }
};
