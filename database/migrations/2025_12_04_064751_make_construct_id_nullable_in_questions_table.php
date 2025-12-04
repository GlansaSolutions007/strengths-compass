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
        Schema::table('questions', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['construct_id']);
            // Make the column nullable
            $table->foreignId('construct_id')->nullable()->change();
            // Recreate the foreign key constraint
            $table->foreign('construct_id')->references('id')->on('constructs')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['construct_id']);
            // Make the column not nullable
            $table->foreignId('construct_id')->nullable(false)->change();
            // Recreate the foreign key constraint
            $table->foreign('construct_id')->references('id')->on('constructs')->onDelete('cascade');
        });
    }
};
