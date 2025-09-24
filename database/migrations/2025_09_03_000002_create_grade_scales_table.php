<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('grade_scales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_system_id')->constrained('grading_systems')->onDelete('cascade');
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');

            // Scale Identity
            $table->string('name', 255);
            $table->enum('scale_type', ['letter', 'percentage', 'points', 'standards']);
            $table->boolean('is_default')->default(false);

            $table->timestamps();

            // Indexes
            $table->index(['grading_system_id']);
            $table->index(['school_id']);
            $table->index(['tenant_id']);
            $table->index(['school_id', 'tenant_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('grade_scales');
    }
};
