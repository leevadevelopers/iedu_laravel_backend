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
        Schema::create('assessment_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('name'); // Teste, Trabalho, Exame, etc.
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->decimal('default_weight', 5, 2)->default(0); // Peso padrão (ex: 20.00%)
            $table->decimal('max_score', 5, 2)->nullable(); // Pontuação máxima padrão
            $table->enum('grading_scale', ['percentage', 'numeric'])->default('percentage'); // Tipo de escala: percentual ou numérico
            $table->string('color')->nullable(); // Para UI
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_types');
    }
};

