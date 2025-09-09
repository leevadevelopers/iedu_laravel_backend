<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('grade_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_scale_id')->constrained('grade_scales')->onDelete('cascade');

            // Grade Definition
            $table->string('grade_value', 10); // 'A', '95', '4.0', 'Exceeds'
            $table->string('display_value', 50);
            $table->decimal('numeric_value', 5, 2);
            $table->decimal('gpa_points', 3, 2)->nullable();

            // Range Definition
            $table->decimal('percentage_min', 5, 2)->nullable();
            $table->decimal('percentage_max', 5, 2)->nullable();

            // Metadata
            $table->text('description')->nullable();
            $table->string('color_code', 7)->nullable(); // Hex color
            $table->boolean('is_passing')->default(true);
            $table->integer('sort_order');

            $table->timestamps();

            // Indexes
            $table->index(['grade_scale_id']);
            $table->index(['sort_order']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('grade_levels');
    }
};
