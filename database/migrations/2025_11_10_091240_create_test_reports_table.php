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
        Schema::create('test_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_result_id')->constrained('test_results')->onDelete('cascade');
            $table->json('radar_data')->nullable()->comment('construct scores for radar chart');
            $table->json('application_matrix')->nullable()->comment('optional precomputed application matrix');
            $table->text('report_summary')->nullable();
            $table->text('recommendations')->nullable();
            $table->string('report_file')->nullable()->comment('link to exported PDF');
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
            
            $table->index('test_result_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_reports');
    }
};
