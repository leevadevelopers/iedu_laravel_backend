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
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            // Use academic_term_id instead of term_id (unified with grade entries)
            $table->foreignId('academic_term_id')->constrained('academic_terms')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');
            $table->foreignId('teacher_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('type_id')->constrained('assessment_types')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->dateTime('scheduled_date')->nullable();
            // Time fields (consolidated from add_time_fields migration)
            $table->time('start_time')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->dateTime('submission_deadline')->nullable();
            $table->decimal('total_marks', 8, 2)->default(100);
            $table->decimal('weight', 5, 2)->default(0); // Peso no cálculo final
            $table->enum('visibility', ['public', 'private', 'tenant'])->default('tenant');
            $table->boolean('allow_upload_submissions')->default(false);
            $table->enum('status', ['draft', 'scheduled', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->boolean('is_locked')->default(false); // Bloqueado após publicação
            $table->dateTime('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->onDelete('set null');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Updated indexes to use academic_term_id
            $table->index(['tenant_id', 'academic_term_id', 'subject_id', 'class_id']);
            $table->index(['tenant_id', 'teacher_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};

