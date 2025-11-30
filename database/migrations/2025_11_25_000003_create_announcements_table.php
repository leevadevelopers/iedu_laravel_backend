<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->string('title');
            $table->text('content');
            $table->json('recipients'); // ['all_parents', 'all_teachers', 'class_ids' => [1,2,3]]
            $table->json('channels'); // ['sms', 'portal', 'whatsapp']
            $table->enum('status', ['draft', 'scheduled', 'published', 'cancelled'])->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->json('metadata')->nullable(); // Track who received/read
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['school_id', 'status']);
            $table->index('scheduled_at');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};

