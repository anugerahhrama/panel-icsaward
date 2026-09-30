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
            $table->timestamp('finalists_confirmed_at')->nullable()->after('paper_template_name');
            $table->foreignId('finalists_confirmed_by')->nullable()->after('finalists_confirmed_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('award_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('finalists_confirmed_by');
            $table->dropColumn('finalists_confirmed_at');
        });
    }
};
