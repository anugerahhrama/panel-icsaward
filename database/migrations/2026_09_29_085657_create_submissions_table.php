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
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('award_category_id')->constrained()->restrictOnDelete();
            $table->string('initiative_title');
            $table->text('initiative_description');
            $table->string('status')->default('registered')->index();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'award_category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
