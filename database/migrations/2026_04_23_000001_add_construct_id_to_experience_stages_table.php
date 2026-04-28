<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experience_stages', function (Blueprint $table) {
            if (!Schema::hasColumn('experience_stages', 'construct_id')) {
                $table->foreignId('construct_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('constructs')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('experience_stages', function (Blueprint $table) {
            if (Schema::hasColumn('experience_stages', 'construct_id')) {
                $table->dropConstrainedForeignId('construct_id');
            }
        });
    }
};
