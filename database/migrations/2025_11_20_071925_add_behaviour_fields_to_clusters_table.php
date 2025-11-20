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
        Schema::table('clusters', function (Blueprint $table) {
            $table->text('high_behaviour')->nullable()->after('description');
            $table->text('medium_behaviour')->nullable()->after('high_behaviour');
            $table->text('low_behaviour')->nullable()->after('medium_behaviour');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clusters', function (Blueprint $table) {
            $table->dropColumn(['high_behaviour', 'medium_behaviour', 'low_behaviour']);
        });
    }
};
