<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            $table->string('account_number', 100)->nullable()->after('code');
            $table->string('bank_name', 255)->nullable()->after('account_number');
            $table->string('bank_branch', 50)->nullable()->after('bank_name');
            $table->string('currency', 3)->default('BRL')->after('bank_branch');
            $table->decimal('initial_balance', 15, 2)->default(0)->after('balance');
        });
    }

    public function down(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropColumn([
                'school_id',
                'account_number',
                'bank_name',
                'bank_branch',
                'currency',
                'initial_balance'
            ]);
        });
    }
};

