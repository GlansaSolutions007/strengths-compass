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
        Schema::table('constructs', function (Blueprint $table) {
            $table->text('medium_behavior')->nullable()->after('high_behavior');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('constructs', function (Blueprint $table) {
            $table->dropColumn('medium_behavior');
        });
    }
};
