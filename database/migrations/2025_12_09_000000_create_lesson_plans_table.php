<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('teacher_id');
            $table->date('week_start');
            $table->string('title', 255);
            $table->string('status', 50)->default('draft');
            $table->string('visibility', 50)->default('private');
            $table->json('day_blocks')->nullable();
            $table->json('objectives')->nullable();
            $table->json('materials')->nullable();
            $table->json('activities')->nullable();
            $table->json('assessment_links')->nullable();
            $table->json('tags')->nullable();
            $table->json('share_with_classes')->nullable();
            $table->text('homework')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('lesson_id')->nullable();
            $table->unsignedBigInteger('copied_from_plan_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'school_id']);
            $table->index(['teacher_id', 'week_start']);
            $table->index(['class_id', 'week_start']);
            $table->index(['subject_id', 'week_start']);
            $table->index(['status', 'visibility']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->foreign('class_id')->references('id')->on('classes')->onDelete('cascade');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
            $table->foreign('teacher_id')->references('id')->on('teachers')->onDelete('cascade');
            $table->foreign('lesson_id')->references('id')->on('lessons')->nullOnDelete();
            $table->foreign('copied_from_plan_id')->references('id')->on('lesson_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_plans');
    }
};

