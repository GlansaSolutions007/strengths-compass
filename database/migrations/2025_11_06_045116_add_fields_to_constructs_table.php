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
            $table->string('short_code')->nullable()->after('name');
            $table->text('definition')->nullable()->after('description');
            $table->text('high_behavior')->nullable()->after('definition');
            $table->text('low_behavior')->nullable()->after('high_behavior');
            $table->text('benefits')->nullable()->after('low_behavior');
            $table->text('risks')->nullable()->after('benefits');
            $table->text('coaching_applications')->nullable()->after('risks');
            $table->text('case_example')->nullable()->after('coaching_applications');
            $table->integer('display_order')->nullable()->after('case_example');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('constructs', function (Blueprint $table) {
            $table->dropColumn([
                'short_code',
                'definition',
                'high_behavior',
                'low_behavior',
                'benefits',
                'risks',
                'coaching_applications',
                'case_example',
                'display_order'
            ]);
        });
    }
};
