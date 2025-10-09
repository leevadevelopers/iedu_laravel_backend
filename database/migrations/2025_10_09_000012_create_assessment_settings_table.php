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
        Schema::create('assessment_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('academic_term_id')->nullable()->constrained('academic_terms')->onDelete('cascade');
            $table->integer('assessments_count')->default(3); // Número de avaliações por período
            $table->decimal('default_passing_score', 5, 2)->default(50.00);
            $table->enum('rounding_policy', ['none', 'up', 'down', 'nearest'])->default('nearest');
            $table->integer('decimal_places')->default(2);
            $table->boolean('allow_grade_review')->default(true);
            $table->integer('review_deadline_days')->default(7); // Dias para pedir revisão
            $table->json('config')->nullable(); // Configurações adicionais
            $table->timestamps();

            $table->index(['tenant_id', 'academic_term_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_settings');
    }
};

