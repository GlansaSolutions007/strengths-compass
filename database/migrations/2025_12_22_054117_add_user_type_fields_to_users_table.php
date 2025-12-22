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
        Schema::table('users', function (Blueprint $table) {
            // Make email nullable for school users (kids may not have email)
            $table->string('email')->nullable()->change();
            
            // Add user type to differentiate individual, school, or organization users
            $table->enum('user_type', ['individual', 'school', 'organization'])->default('individual')->after('role');
            
            // Add foreign keys for school and organization
            $table->foreignId('school_id')->nullable()->after('user_type')->constrained('schools')->onDelete('set null');
            $table->foreignId('organization_id')->nullable()->after('school_id')->constrained('organizations')->onDelete('set null');
            
            // Add index for better query performance
            $table->index('user_type');
            $table->index('school_id');
            $table->index('organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['user_type']);
            $table->dropIndex(['school_id']);
            $table->dropIndex(['organization_id']);
            $table->dropColumn(['user_type', 'school_id', 'organization_id']);
            // Note: email nullable change might need separate migration to revert
        });
    }
};
