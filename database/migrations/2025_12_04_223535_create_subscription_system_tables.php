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
        Schema::create('subscription_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('duration_days');
            $table->decimal('price', 10, 2);
            $table->json('features')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_package_id')
                  ->constrained('subscription_packages')
                  ->onDelete('restrict');

            // Subscription applies to either a tenant OR a project
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->onDelete('cascade');
            $table->foreignId('project_id')->nullable()->constrained()->onDelete('cascade');

            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('auto_renew')->default(false);
            $table->enum('status', ['active', 'expired', 'cancelled', 'pending'])->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Ensure a tenant does not have multiple active subscriptions
            $table->unique(['tenant_id', 'status'], 'unique_tenant_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_packages');
    }
};
