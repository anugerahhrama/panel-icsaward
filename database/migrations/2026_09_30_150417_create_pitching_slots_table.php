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
        Schema::create('pitching_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pitching_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submission_id')->unique()->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pitching_slots');
    }
};
