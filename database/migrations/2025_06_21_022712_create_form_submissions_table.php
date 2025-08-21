<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFormSubmissionsTable extends Migration
{
    public function up()
    {
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('form_instance_id')->constrained()->onDelete('cascade');
            $table->foreignId('submitted_by')->constrained('users')->onDelete('cascade');
            $table->json('submission_data');
            $table->json('attachments')->nullable();
            $table->text('notes')->nullable();
            $table->enum('submission_type', ['save', 'submit', 'auto_save'])->default('submit');
            $table->timestamps();
            
            $table->index(['tenant_id', 'created_at']);
            $table->index(['form_instance_id', 'submission_type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('form_submissions');
    }
}