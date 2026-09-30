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
            $table->text('revision_note')->nullable()->after('status');
            $table->timestamp('revision_deadline')->nullable()->after('revision_note');
            $table->text('disqualified_reason')->nullable()->after('revision_deadline');
            $table->timestamp('reviewed_at')->nullable()->after('disqualified_reason');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('notified_at')->nullable()->after('reviewed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'revision_note',
                'revision_deadline',
                'disqualified_reason',
                'reviewed_at',
                'notified_at',
            ]);
        });
    }
};
