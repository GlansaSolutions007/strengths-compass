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
        Schema::table('test_results', function (Blueprint $table) {
            $table->float('sdb_raw_score', 8, 2)->nullable()->after('sdb_flag')->comment('SDB Raw Score: Sum of 18 SDB items / 18');
            $table->float('sdb_percentage', 5, 2)->nullable()->after('sdb_raw_score')->comment('SDB Percentage: ((raw_score - 1) / 4) * 100');
            $table->enum('sdb_band', ['GREEN', 'AMBER', 'RED'])->nullable()->after('sdb_percentage')->comment('GREEN: 0-70%, AMBER: 71-85%, RED: 86-100%');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('test_results', function (Blueprint $table) {
            $table->dropColumn(['sdb_raw_score', 'sdb_percentage', 'sdb_band']);
        });
    }
};
