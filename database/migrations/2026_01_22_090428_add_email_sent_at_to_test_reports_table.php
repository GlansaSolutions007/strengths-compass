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
        Schema::table('test_reports', function (Blueprint $table) {
            $table->timestamp('email_sent_at')->nullable()->after('generated_at')->comment('Timestamp when email with PDF was successfully sent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('test_reports', function (Blueprint $table) {
            $table->dropColumn('email_sent_at');
        });
    }
};
