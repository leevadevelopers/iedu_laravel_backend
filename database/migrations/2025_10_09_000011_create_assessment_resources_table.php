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
        Schema::create('assessment_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->onDelete('cascade');
            $table->enum('type', ['file', 'link', 'video', 'document'])->default('file');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('url_or_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->enum('access_policy', ['teacher_only', 'class', 'tenant', 'public'])->default('class');
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index(['assessment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_resources');
    }
};

