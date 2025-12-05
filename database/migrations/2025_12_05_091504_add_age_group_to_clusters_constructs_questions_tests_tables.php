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
        // Add age_group_id to clusters table
        if (!Schema::hasColumn('clusters', 'age_group_id')) {
            Schema::table('clusters', function (Blueprint $table) {
                $table->foreignId('age_group_id')->nullable()->after('description')->constrained('age_groups')->onDelete('set null');
            });
        }

        // Add age_group_id to constructs table
        if (!Schema::hasColumn('constructs', 'age_group_id')) {
            Schema::table('constructs', function (Blueprint $table) {
                $table->foreignId('age_group_id')->nullable()->after('description')->constrained('age_groups')->onDelete('set null');
            });
        }

        // Add age_group_id to questions table
        if (!Schema::hasColumn('questions', 'age_group_id')) {
            Schema::table('questions', function (Blueprint $table) {
                $table->foreignId('age_group_id')->nullable()->after('question_text')->constrained('age_groups')->onDelete('set null');
            });
        }

        // Add age_group_id to tests table
        if (!Schema::hasColumn('tests', 'age_group_id')) {
            Schema::table('tests', function (Blueprint $table) {
                $table->foreignId('age_group_id')->nullable()->after('description')->constrained('age_groups')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove age_group_id from clusters table
        Schema::table('clusters', function (Blueprint $table) {
            $table->dropForeign(['age_group_id']);
            $table->dropColumn('age_group_id');
        });

        // Remove age_group_id from constructs table
        Schema::table('constructs', function (Blueprint $table) {
            $table->dropForeign(['age_group_id']);
            $table->dropColumn('age_group_id');
        });

        // Remove age_group_id from questions table
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['age_group_id']);
            $table->dropColumn('age_group_id');
        });

        // Remove age_group_id from tests table
        Schema::table('tests', function (Blueprint $table) {
            $table->dropForeign(['age_group_id']);
            $table->dropColumn('age_group_id');
        });
    }
};
