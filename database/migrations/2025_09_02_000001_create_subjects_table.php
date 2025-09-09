<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');

            // Subject Identity
            $table->string('name', 255);
            $table->string('code', 50);
            $table->text('description')->nullable();

            // Academic Classification
            $table->enum('subject_area', [
                'mathematics', 'science', 'language_arts', 'social_studies',
                'foreign_language', 'arts', 'physical_education', 'technology',
                'vocational', 'other'
            ]);
            $table->json('grade_levels'); // Array of applicable grades

            // Academic Standards
            $table->json('learning_standards_json')->nullable(); // Curriculum standards alignment
            $table->json('prerequisites')->nullable(); // Required prior knowledge/courses

            // Configuration
            $table->decimal('credit_hours', 3, 1)->default(1.0);
            $table->boolean('is_core_subject')->default(false);
            $table->boolean('is_elective')->default(false);

            // Status
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');

            $table->timestamps();

            // Constraints
            $table->unique(['school_id', 'code']);

            // Indexes
            $table->index(['school_id']);
            $table->index(['subject_area']);
            $table->index(['status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('subjects');
    }
};
