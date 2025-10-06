<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['pdf', 'epub', 'audio', 'video'])->default('pdf');
            $table->string('file_path')->nullable();
            $table->string('external_url')->nullable();
            $table->bigInteger('size')->nullable();
            $table->string('mime')->nullable();
            $table->enum('access_policy', ['public', 'tenant_only', 'specific_roles'])->default('tenant_only');
            $table->json('allowed_roles')->nullable();
            $table->timestamps();

            $table->index('book_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_files');
    }
};
