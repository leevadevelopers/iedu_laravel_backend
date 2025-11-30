<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->string('name');
            $table->enum('type', ['parent', 'vendor', 'official', 'other'])->default('other');
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('purpose');
            $table->foreignId('attended_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('resolved')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('departed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['school_id', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};

