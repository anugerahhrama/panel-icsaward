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
        Schema::table('award_categories', function (Blueprint $table) {
            $table->foreignId('assessment_template_id')->nullable()->after('applicant_type')->constrained()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('award_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assessment_template_id');
        });
    }
};
