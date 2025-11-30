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
        // Adicionar school_id à tabela invoices
        if (Schema::hasTable('invoices') && !Schema::hasColumn('invoices', 'school_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreignId('school_id')->after('tenant_id')->nullable()->constrained('schools')->cascadeOnDelete();
                $table->index(['school_id', 'status']);
            });
        }

        // Adicionar school_id à tabela payments
        if (Schema::hasTable('payments') && !Schema::hasColumn('payments', 'school_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->foreignId('school_id')->after('tenant_id')->nullable()->constrained('schools')->cascadeOnDelete();
                $table->index(['school_id', 'status']);
            });
        }

        // Adicionar school_id à tabela expenses
        if (Schema::hasTable('expenses') && !Schema::hasColumn('expenses', 'school_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->foreignId('school_id')->after('tenant_id')->nullable()->constrained('schools')->cascadeOnDelete();
                $table->index(['school_id', 'category']);
            });
        }

        // Adicionar school_id à tabela fees
        if (Schema::hasTable('fees') && !Schema::hasColumn('fees', 'school_id')) {
            Schema::table('fees', function (Blueprint $table) {
                $table->foreignId('school_id')->after('tenant_id')->nullable()->constrained('schools')->cascadeOnDelete();
                $table->index(['school_id', 'is_active']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover school_id da tabela fees
        if (Schema::hasColumn('fees', 'school_id')) {
            Schema::table('fees', function (Blueprint $table) {
                $table->dropForeign(['school_id']);
                $table->dropIndex(['school_id', 'is_active']);
                $table->dropColumn('school_id');
            });
        }

        // Remover school_id da tabela expenses
        if (Schema::hasColumn('expenses', 'school_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropForeign(['school_id']);
                $table->dropIndex(['school_id', 'category']);
                $table->dropColumn('school_id');
            });
        }

        // Remover school_id da tabela payments
        if (Schema::hasColumn('payments', 'school_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropForeign(['school_id']);
                $table->dropIndex(['school_id', 'status']);
                $table->dropColumn('school_id');
            });
        }

        // Remover school_id da tabela invoices
        if (Schema::hasColumn('invoices', 'school_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropForeign(['school_id']);
                $table->dropIndex(['school_id', 'status']);
                $table->dropColumn('school_id');
            });
        }
    }
};
