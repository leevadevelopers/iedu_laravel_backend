<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('student_class_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');

            $table->date('enrollment_date');
            $table->enum('status', ['active', 'dropped', 'completed'])->default('active');
            $table->string('final_grade', 5)->nullable();

            $table->timestamps();

            // Unique enrollment per student per class
            $table->unique(['student_id', 'class_id']);

            // Indexes
            $table->index(['student_id']);
            $table->index(['class_id']);
            $table->index(['status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('student_class_enrollments');
    }
};
