<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assessment_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->onDelete('cascade');
            $table->string('name'); // Ex: Teórica, Prática, Oral
            $table->text('description')->nullable();
            $table->decimal('weight_pct', 5, 2); // Peso em percentagem (ex: 40.00%)
            $table->decimal('max_marks', 8, 2); // Nota máxima deste componente
            $table->json('rubric')->nullable(); // Critérios de avaliação
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index(['assessment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_components');
    }
};

