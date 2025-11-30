<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('payment_id')->unique();
            $table->string('reference_code')->nullable();
            $table->enum('provider', ['mpesa', 'emola', 'other'])->default('mpesa');
            $table->decimal('amount', 15, 2);
            $table->string('phone', 20);
            $table->enum('status', ['pending', 'initiated', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->string('transaction_id')->nullable();
            $table->text('instructions')->nullable();
            $table->text('provider_response')->nullable();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('payment_id_fk')->nullable()->constrained('payments')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['school_id', 'status']);
            $table->index(['student_id', 'status']);
            $table->index('payment_id');
            $table->index('transaction_id');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_payments');
    }
};

