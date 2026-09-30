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
            $table->timestamp('confirmation_sent_at')->nullable()->after('terms_accepted_at');
            $table->string('paper_path')->nullable()->after('confirmation_sent_at');
            $table->string('paper_original_name')->nullable()->after('paper_path');
            $table->string('statement_path')->nullable()->after('paper_original_name');
            $table->string('statement_original_name')->nullable()->after('statement_path');
            $table->timestamp('paper_uploaded_at')->nullable()->after('statement_original_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn([
                'confirmation_sent_at',
                'paper_path',
                'paper_original_name',
                'statement_path',
                'statement_original_name',
                'paper_uploaded_at',
            ]);
        });
    }
};
