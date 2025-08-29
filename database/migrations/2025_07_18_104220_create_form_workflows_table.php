<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('form_instance_id')->nullable()->constrained('form_instances')->onDelete('set null');
            $table->string('workflow_type')->nullable();
            $table->integer('current_step')->default(1);
            $table->integer('total_steps')->default(1);
            $table->json('steps_configuration_json')->nullable();
            $table->json('current_step_data_json')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->integer('escalation_level')->nullable();
            $table->string('escalation_reason')->nullable();
            $table->timestamps();

            $table->index(['form_instance_id']);
            $table->index(['workflow_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_workflows');
    }
};
