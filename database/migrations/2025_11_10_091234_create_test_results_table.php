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
        Schema::create('test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('test_id')->constrained('tests')->onDelete('cascade');
            $table->float('total_score', 10, 2)->nullable();
            $table->float('average_score', 8, 2)->nullable();
            $table->json('cluster_scores')->nullable()->comment('{"Caring & Connection": 82.5, ...}');
            $table->json('construct_scores')->nullable()->comment('{"Perseverance": 4.0, ...}');
            $table->boolean('sdb_flag')->default(false);
            $table->enum('status', ['pending', 'completed', 'reviewed'])->default('completed');
            $table->foreignId('expert_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('test_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_results');
    }
};
