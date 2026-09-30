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
            $table->decimal('final_score', 7, 4)->nullable()->after('stage2_calculated_at');
            $table->unsignedSmallInteger('final_rank')->nullable()->after('final_score');
            $table->timestamp('final_calculated_at')->nullable()->after('final_rank');
            $table->string('award')->nullable()->after('final_calculated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['final_score', 'final_rank', 'final_calculated_at', 'award']);
        });
    }
};
