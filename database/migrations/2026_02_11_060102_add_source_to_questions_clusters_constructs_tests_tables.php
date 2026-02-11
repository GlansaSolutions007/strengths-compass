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
        // Add source to clusters table
        if (!Schema::hasColumn('clusters', 'source')) {
            Schema::table('clusters', function (Blueprint $table) {
                $table->enum('source', ['SC Pro', 'CERC'])->default('SC Pro')->after('description');
            });
        }

        // Add source to constructs table
        if (!Schema::hasColumn('constructs', 'source')) {
            Schema::table('constructs', function (Blueprint $table) {
                $table->enum('source', ['SC Pro', 'CERC'])->default('SC Pro')->after('description');
            });
        }

        // Add source to questions table
        if (!Schema::hasColumn('questions', 'source')) {
            Schema::table('questions', function (Blueprint $table) {
                $table->enum('source', ['SC Pro', 'CERC'])->default('SC Pro')->after('question_text');
            });
        }

        // Add source to tests table
        if (!Schema::hasColumn('tests', 'source')) {
            Schema::table('tests', function (Blueprint $table) {
                $table->enum('source', ['SC Pro', 'CERC'])->default('SC Pro')->after('description');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove source from clusters table
        if (Schema::hasColumn('clusters', 'source')) {
            Schema::table('clusters', function (Blueprint $table) {
                $table->dropColumn('source');
            });
        }

        // Remove source from constructs table
        if (Schema::hasColumn('constructs', 'source')) {
            Schema::table('constructs', function (Blueprint $table) {
                $table->dropColumn('source');
            });
        }

        // Remove source from questions table
        if (Schema::hasColumn('questions', 'source')) {
            Schema::table('questions', function (Blueprint $table) {
                $table->dropColumn('source');
            });
        }

        // Remove source from tests table
        if (Schema::hasColumn('tests', 'source')) {
            Schema::table('tests', function (Blueprint $table) {
                $table->dropColumn('source');
            });
        }
    }
};
