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
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'school_id')) {
                $table->foreignId('school_id')->after('tenant_id')->nullable()->constrained('schools')->cascadeOnDelete();
                $table->index(['school_id', 'account_id']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'school_id')) {
                $table->dropForeign(['school_id']);
                $table->dropIndex(['school_id', 'account_id']);
                $table->dropColumn('school_id');
            }
        });
    }
};
