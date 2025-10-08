<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('barcode')->unique();
            $table->string('location')->nullable();
            $table->enum('status', ['available', 'loaned', 'reserved', 'lost', 'maintenance'])->default('available');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['book_id', 'status']);
            $table->index('barcode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_copies');
    }
};
