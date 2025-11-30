<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->string('template');
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('purpose')->nullable();
            $table->string('signed_by')->nullable();
            $table->text('notes')->nullable();
            $table->string('document_id')->unique();
            $table->string('download_url')->nullable();
            $table->string('pdf_url')->nullable();
            $table->enum('status', ['draft', 'generated', 'sent', 'archived'])->default('draft');
            $table->foreignId('generated_by')->constrained('users')->cascadeOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['school_id', 'status']);
            $table->index('student_id');
            $table->index('template');
            $table->index('document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};

