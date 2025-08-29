<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAcademicYearTables extends Migration
{
    public function up()
    {

          // Create academic_years table (Time-based educational structure)
          Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');

            // Academic Year Identity
            $table->string('name', 100); // "2025-2026", "Academic Year 2025"
            $table->string('code', 20); // "AY2025", "2025-26"

            // Date Boundaries
            $table->date('start_date');
            $table->date('end_date');

            // Academic Structure
            $table->enum('term_structure', [
                'semesters', 'trimesters', 'quarters', 'year_round'
            ]);
            $table->integer('total_terms')->default(2);
            $table->integer('total_instructional_days')->nullable();

            // Status & Current
            $table->enum('status', [
                'planning', 'active', 'completed', 'archived'
            ])->default('planning');
            $table->boolean('is_current')->default(false);

            $table->timestamps();

            // Constraints
            $table->unique(['school_id', 'code']);

            // Indexes
            $table->index('school_id');
            $table->index('is_current');
            $table->index('status');
            $table->index(['start_date', 'end_date']);
        });

        // Create academic_terms table (Terms/Periods)
        Schema::create('academic_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->onDelete('cascade');
            $table->foreignId('school_id')->constrained()->onDelete('cascade');

            // Term Identity
            $table->string('name', 100); // "Fall Semester", "First Quarter"
            $table->integer('term_number');

            // Date Boundaries
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('instructional_days');

            // Status
            $table->enum('status', [
                'planned', 'active', 'completed'
            ])->default('planned');

            $table->timestamps();

            // Indexes
            $table->index('academic_year_id');
            $table->index('school_id');
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('academic_terms');
        Schema::dropIfExists('academic_years');

    }
}
