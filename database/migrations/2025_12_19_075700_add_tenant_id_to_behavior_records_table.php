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
        Schema::table('behavior_records', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                ->after('id')
                ->constrained('tenants')
                ->onDelete('cascade');

            $table->index(['tenant_id', 'school_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('behavior_records', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id', 'school_id']);
            $table->dropColumn('tenant_id');
        });
    }
};

