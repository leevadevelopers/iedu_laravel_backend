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
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');

            // Scale Identity
            $table->string('name', 255);
            $table->string('code', 50)->nullable();
            $table->text('description')->nullable();
            $table->enum('scale_type', ['letter', 'percentage', 'points', 'standards']);
            $table->boolean('is_default')->default(false);
            
            // Values
            $table->decimal('min_value', 5, 2)->nullable();
            $table->decimal('max_value', 5, 2)->nullable();
            $table->decimal('passing_grade', 5, 2)->nullable();
            
            // Status and Configuration
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->json('configuration_json')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['school_id']);
            $table->index(['tenant_id']);
            $table->index(['school_id', 'tenant_id']);
            $table->index(['scale_type']);
            $table->index(['status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('grade_scales');
    }
};
