<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('grade_scale_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_scale_id')->constrained('grade_scales')->onDelete('cascade');
            
            // Range Definition
            $table->decimal('min_value', 8, 2); // Valor mínimo do intervalo
            $table->decimal('max_value', 8, 2); // Valor máximo do intervalo
            $table->string('display_label', 10); // A, B, C ou 18, 15, etc.
            $table->string('description', 255)->nullable(); // Excelente, Bom, etc.
            
            // Additional Properties
            $table->string('color', 7)->nullable(); // Cor para UI (#10B981)
            $table->decimal('gpa_equivalent', 3, 2)->nullable(); // Equivalente GPA (0.00-4.00)
            $table->boolean('is_passing')->default(true); // Se é nota de aprovação
            $table->integer('order')->default(0); // Ordem de exibição
            
            $table->timestamps();
            
            // Indexes
            $table->index(['grade_scale_id']);
            $table->index(['min_value', 'max_value']);
            $table->index(['order']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('grade_scale_ranges');
    }
};
