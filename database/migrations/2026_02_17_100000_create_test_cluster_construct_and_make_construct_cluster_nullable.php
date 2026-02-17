<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Makes cluster and construct independent: cluster-construct assignment is per-test
     * via test_cluster_construct. constructs.cluster_id becomes nullable for backward compatibility.
     */
    public function up(): void
    {
        // Pivot: for each test, which constructs belong to which clusters
        Schema::create('test_cluster_construct', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id')->constrained('tests')->onDelete('cascade');
            $table->foreignId('cluster_id')->constrained('clusters')->onDelete('cascade');
            $table->foreignId('construct_id')->constrained('constructs')->onDelete('cascade');
            $table->timestamps();

            // A construct can appear in only one cluster per test
            $table->unique(['test_id', 'construct_id']);
        });

        // Make construct's cluster_id nullable so constructs can exist without a global cluster
        Schema::table('constructs', function (Blueprint $table) {
            $table->dropForeign(['cluster_id']);
            $table->foreignId('cluster_id')->nullable()->change();
            $table->foreign('cluster_id')->references('id')->on('clusters')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('constructs', function (Blueprint $table) {
            $table->dropForeign(['cluster_id']);
            $table->foreignId('cluster_id')->nullable(false)->change();
            $table->foreign('cluster_id')->references('id')->on('clusters')->onDelete('cascade');
        });

        Schema::dropIfExists('test_cluster_construct');
    }
};
