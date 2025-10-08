<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('collection_id')->nullable()->constrained('library_collections')->nullOnDelete();
            $table->foreignId('publisher_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('isbn')->unique()->nullable();
            $table->string('language', 10)->default('pt');
            $table->text('summary')->nullable();
            $table->enum('visibility', ['public', 'tenant', 'restricted'])->default('tenant');
            $table->json('restricted_tenants')->nullable();
            $table->json('subjects')->nullable();
            $table->date('published_at')->nullable();
            $table->string('edition')->nullable();
            $table->integer('pages')->nullable();
            $table->string('cover_image')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'visibility']);
            $table->index('isbn');
            $table->fullText(['title', 'summary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
