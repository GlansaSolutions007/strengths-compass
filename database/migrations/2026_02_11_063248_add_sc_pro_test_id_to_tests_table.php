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
        if (!Schema::hasColumn('tests', 'sc_pro_test_id')) {
            Schema::table('tests', function (Blueprint $table) {
                $table->foreignId('sc_pro_test_id')
                    ->nullable()
                    ->after('source')
                    ->constrained('tests')
                    ->onDelete('set null')
                    ->comment('For CERC tests: links to the corresponding SC Pro test');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('tests', 'sc_pro_test_id')) {
            Schema::table('tests', function (Blueprint $table) {
                $table->dropForeign(['sc_pro_test_id']);
                $table->dropColumn('sc_pro_test_id');
            });
        }
    }
};
