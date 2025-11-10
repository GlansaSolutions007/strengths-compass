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
        Schema::create('user_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_result_id')->constrained('test_results')->onDelete('cascade');
            $table->foreignId('question_id')->constrained('questions')->onDelete('cascade');
            $table->integer('answer_value')->comment('raw 1..5');
            $table->float('final_score', 8, 2)->comment('adjusted for reverse/weight');
            $table->timestamps();
            
            $table->index('test_result_id');
            $table->index('question_id');
            $table->unique(['test_result_id', 'question_id']); // One answer per question per test result
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_answers');
    }
};
