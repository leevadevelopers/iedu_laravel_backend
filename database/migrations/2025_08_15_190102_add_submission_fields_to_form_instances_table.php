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
        Schema::table('form_instances', function (Blueprint $table) {
            $table->string('submission_type')->default('internal')->after('created_by');
            $table->json('submission_metadata')->nullable()->after('submission_type');
            
            // Add index for submission type lookups
            $table->index(['submission_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_instances', function (Blueprint $table) {
            $table->dropIndex(['submission_type', 'status']);
            $table->dropColumn(['submission_type', 'submission_metadata']);
        });
    }
};
