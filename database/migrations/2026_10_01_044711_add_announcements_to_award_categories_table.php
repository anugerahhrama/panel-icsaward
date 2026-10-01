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
            $table->timestamp('finalists_announced_at')->nullable()->after('awards_confirmed_by');
            $table->foreignId('finalists_announced_by')->nullable()->after('finalists_announced_at')->constrained('users')->nullOnDelete();
            $table->timestamp('invitations_sent_at')->nullable()->after('finalists_announced_by');
            $table->foreignId('invitations_sent_by')->nullable()->after('invitations_sent_at')->constrained('users')->nullOnDelete();
            $table->timestamp('winners_announced_at')->nullable()->after('invitations_sent_by');
            $table->foreignId('winners_announced_by')->nullable()->after('winners_announced_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('award_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('finalists_announced_by');
            $table->dropConstrainedForeignId('invitations_sent_by');
            $table->dropConstrainedForeignId('winners_announced_by');
            $table->dropColumn(['finalists_announced_at', 'invitations_sent_at', 'winners_announced_at']);
        });
    }
};
