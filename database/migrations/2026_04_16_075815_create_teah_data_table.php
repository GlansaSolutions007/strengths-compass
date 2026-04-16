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
        Schema::create('teacher_data', function (Blueprint $table) {
            $table->id();

            // User reference
            $table->unsignedBigInteger('tch_user_id');

            // Teaching details
            $table->string('tch_teaching_subject');
            $table->string('tch_teaching_level');

            // Experience
            $table->unsignedSmallInteger('tch_years_of_experience')->nullable();

            // Current role
            $table->string('tch_current_role')->nullable();

            // School details
            $table->string('tch_school_context')->nullable(); // Government / Private / etc
            $table->string('tch_school_name')->nullable();
            $table->string('tch_school_city')->nullable();
            $table->string('tch_school_state')->nullable();

            // Consent
            $table->boolean('tch_consent')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_data');
    }
};