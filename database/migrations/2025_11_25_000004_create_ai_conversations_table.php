<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->uuid('conversation_id')->unique();
            $table->string('subject')->nullable();
            $table->text('question');
            $table->text('answer');
            $table->text('explanation')->nullable();
            $table->json('examples')->nullable();
            $table->json('practice_problems')->nullable();
            $table->string('audio_url')->nullable();
            $table->string('image_url')->nullable();
            $table->json('context')->nullable(); // grade_level, topic, etc.
            $table->integer('tokens_used')->default(0);
            $table->decimal('cost', 10, 6)->nullable();
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'student_id']);
            $table->index(['school_id', 'student_id']);
            $table->index('conversation_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversations');
    }
};

