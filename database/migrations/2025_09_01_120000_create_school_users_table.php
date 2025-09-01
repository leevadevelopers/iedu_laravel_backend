<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSchoolUsersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('school_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('role', [
                'teacher', 'admin', 'staff', 'principal', 'counselor',
                'nurse', 'librarian', 'coach', 'volunteer'
            ])->default('staff');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->json('permissions')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_users');
    }
}
