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
        Schema::table('test_cluster', function (Blueprint $table) {
            $table->integer('p_count')->nullable()->after('cluster_id')->comment('Number of Positive questions to select');
            $table->integer('r_count')->nullable()->after('p_count')->comment('Number of Reverse questions to select');
            $table->integer('sdb_count')->nullable()->after('r_count')->comment('Number of Social Desirability Bias questions to select');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('test_cluster', function (Blueprint $table) {
            $table->dropColumn(['p_count', 'r_count', 'sdb_count']);
        });
    }
};
