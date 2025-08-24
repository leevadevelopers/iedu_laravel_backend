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
        Schema::create('form_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('workflow_id')->constrained('form_workflows')->onDelete('cascade');
            $table->integer('step_number');
            $table->string('step_name');
            $table->string('step_type'); // approval, review, etc.
            $table->string('required_role')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('instructions')->nullable();
            $table->json('required_actions_json')->nullable();
            $table->boolean('form_modifications_allowed')->default(false);
            $table->enum('status', ['pending', 'in_progress', 'completed', 'skipped', 'escalated'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('decision')->nullable(); // approved, rejected, escalated
            $table->text('comments')->nullable();
            $table->foreignId('decision_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('decision_date')->nullable();
            $table->json('attachments_json')->nullable();
            $table->json('evidence_json')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['workflow_id', 'step_number']);
            $table->index(['workflow_id', 'status']);
            $table->index(['assigned_user_id', 'status']);
            $table->index(['tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_workflow_steps');
    }
};
