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
        // Add age_group to clusters table
        Schema::table('clusters', function (Blueprint $table) {
            $table->string('age_group')->nullable()->after('description');
        });

        // Add age_group to constructs table
        Schema::table('constructs', function (Blueprint $table) {
            $table->string('age_group')->nullable()->after('description');
        });

        // Add age_group to questions table
        Schema::table('questions', function (Blueprint $table) {
            $table->string('age_group')->nullable()->after('question_text');
        });

        // Add age_group to tests table
        Schema::table('tests', function (Blueprint $table) {
            $table->string('age_group')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove age_group from clusters table
        Schema::table('clusters', function (Blueprint $table) {
            $table->dropColumn('age_group');
        });

        // Remove age_group from constructs table
        Schema::table('constructs', function (Blueprint $table) {
            $table->dropColumn('age_group');
        });

        // Remove age_group from questions table
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('age_group');
        });

        // Remove age_group from tests table
        Schema::table('tests', function (Blueprint $table) {
            $table->dropColumn('age_group');
        });
    }
};
