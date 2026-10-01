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
            $table->timestamp('finalist_notified_at')->nullable()->after('award');
            $table->timestamp('invitation_notified_at')->nullable()->after('finalist_notified_at');
            $table->timestamp('award_notified_at')->nullable()->after('invitation_notified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['finalist_notified_at', 'invitation_notified_at', 'award_notified_at']);
        });
    }
};
