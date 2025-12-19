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
        Schema::create('behavior_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignId('lesson_session_id')->constrained('lesson_sessions')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            
            // Behavior tracking
            $table->integer('points')->default(0); // +1, -1, +2, -2, etc.
            $table->enum('type', ['positive', 'negative'])->nullable(); // derived from points
            
            // Optional categorization (future)
            $table->string('category', 50)->nullable(); // participation, disruption, homework, etc.
            $table->text('note')->nullable();
            
            // Metadata
            $table->timestamp('recorded_at')->default(now());
            $table->foreignId('recorded_by')->constrained('users')->onDelete('cascade');
            
            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            
            // Indexes
            $table->index('lesson_session_id');
            $table->index('student_id');
            $table->index('type');
            $table->index(['school_id', 'lesson_session_id']);
            $table->index(['student_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('behavior_records');
    }
};
