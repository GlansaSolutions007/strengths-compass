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
        Schema::create('scoring_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('questions')->onDelete('cascade');
            $table->enum('category', ['P', 'R', 'SDB'])->nullable()->comment('Optional redundancy for quick queries');
            $table->boolean('reverse_score')->default(false);
            $table->boolean('include_in_construct')->default(true)->comment('false for SDB');
            $table->float('weight', 8, 2)->default(1.0);
            $table->timestamps();
            
            $table->index('question_id');
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scoring_rules');
    }
};
