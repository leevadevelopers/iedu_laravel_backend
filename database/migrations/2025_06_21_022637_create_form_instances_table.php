<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFormInstancesTable extends Migration
{
    public function up()
    {
        Schema::create('form_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('form_template_id')->constrained()->onDelete('cascade');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('form_type')->nullable();
            $table->foreignId('organization_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('instance_code')->unique();
            $table->json('form_data');
            $table->json('calculated_fields')->nullable();
            $table->enum('status', [
                'draft',
                'in_progress',
                'submitted',
                'under_review',
                'approved',
                'rejected',
                'completed'
            ])->default('draft');
            $table->string('workflow_state')->nullable();
            $table->json('workflow_history')->nullable();
            $table->integer('current_step')->default(1);
            $table->float('completion_percentage', 5, 2)->default(0);
            $table->json('validation_results')->nullable();
            $table->json('compliance_results')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['form_template_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['form_type']);
            $table->index(['organization_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('form_instances');
    }
}
