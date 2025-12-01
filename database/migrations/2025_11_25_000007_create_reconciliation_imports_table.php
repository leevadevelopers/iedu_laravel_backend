<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->uuid('import_id')->unique();
            $table->enum('provider', ['mpesa', 'emola', 'other'])->default('mpesa');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('file_path')->nullable();
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->integer('total_transactions')->default(0);
            $table->integer('matched')->default(0);
            $table->integer('unmatched')->default(0);
            $table->integer('pending')->default(0);
            $table->foreignId('imported_by')->constrained('users')->cascadeOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['school_id', 'status']);
            $table->index('import_id');
            $table->index('provider');
        });

        Schema::create('reconciliation_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_import_id')->constrained('reconciliation_imports')->cascadeOnDelete();
            $table->string('transaction_id')->unique();
            $table->decimal('amount', 15, 2);
            $table->string('phone', 20);
            $table->timestamp('transaction_date');
            $table->string('description')->nullable();
            $table->enum('match_status', ['pending', 'matched', 'unmatched', 'confirmed'])->default('pending');
            $table->foreignId('matched_student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('matched_payment_id')->nullable()->constrained('mobile_payments')->nullOnDelete();
            $table->string('confidence')->nullable(); // high, medium, low
            $table->json('match_details')->nullable();
            $table->timestamps();

            $table->index(
                ['reconciliation_import_id', 'match_status'],
                'recon_tx_import_match_idx'
            );
            $table->index('transaction_id');
            $table->index('phone');
            $table->index('transaction_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_transactions');
        Schema::dropIfExists('reconciliation_imports');
    }
};

