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
        Schema::create('judge_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('judge_id')->constrained()->restrictOnDelete();
            $table->foreignId('scoring_criterion_id')->constrained('scoring_criteria')->restrictOnDelete();
            $table->string('stage');
            $table->unsignedTinyInteger('raw_score')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['submission_id', 'judge_id', 'scoring_criterion_id', 'stage'], 'judge_scores_unique_score');
            $table->index(['judge_id', 'stage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('judge_scores');
    }
};
