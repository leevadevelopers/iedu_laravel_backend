<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignId('lesson_id')->constrained('lessons')->onDelete('cascade');

            // Content Details
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('content_type', [
                'document', 'video', 'audio', 'link', 'image',
                'presentation', 'worksheet', 'quiz', 'assignment',
                'meeting_recording', 'live_stream', 'external_resource'
            ]);

            // File Information (for uploaded content)
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_type', 10)->nullable(); // pdf, docx, mp4, etc.
            $table->unsignedBigInteger('file_size')->nullable(); // in bytes
            $table->string('mime_type', 100)->nullable();

            // URL Information (for links and external resources)
            $table->text('url')->nullable();
            $table->string('thumbnail_url', 500)->nullable();
            $table->json('embed_data')->nullable(); // YouTube embed, etc.

            // Content Organization
            $table->string('category', 100)->nullable(); // "Required Reading", "Supplementary", etc.
            $table->integer('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_downloadable')->default(true);

            // Access Control
            $table->boolean('is_public')->default(false); // Visible to parents/guardians
            $table->json('access_permissions')->nullable(); // Custom permissions
            $table->date('available_from')->nullable();
            $table->date('available_until')->nullable();

            // Metadata
            $table->json('metadata')->nullable(); // Duration, size, etc.
            $table->text('notes')->nullable();
            $table->json('tags')->nullable();

            // Audit
            $table->foreignId('uploaded_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->timestamps();

            // Indexes
            $table->index(['lesson_id', 'content_type']);
            $table->index(['school_id', 'content_type']);
            $table->index(['sort_order']);
            $table->index(['is_required', 'is_public']);
            $table->index(['available_from', 'available_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_contents');
    }
};
