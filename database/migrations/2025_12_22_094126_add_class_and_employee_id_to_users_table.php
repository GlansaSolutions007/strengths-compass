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
            // Class/Standard for school users (e.g., "10th", "12th", "Class 5")
            $table->string('class')->nullable()->after('organization_id');
            
            // Employee ID for organization users
            $table->string('employee_id')->nullable()->after('class');
            
            // Registration number for school users
            $table->string('registration_no')->nullable()->after('employee_id');
            
            // Add indexes
            $table->index('class');
            $table->index('employee_id');
            $table->index('registration_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['class']);
            $table->dropIndex(['employee_id']);
            $table->dropIndex(['registration_no']);
            $table->dropColumn(['class', 'employee_id', 'registration_no']);
        });
    }
};

