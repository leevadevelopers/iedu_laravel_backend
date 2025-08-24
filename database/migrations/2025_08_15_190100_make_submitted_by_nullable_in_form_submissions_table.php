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
        Schema::table('form_submissions', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['submitted_by']);
            
            // Modify the column to be nullable
            $table->foreignId('submitted_by')->nullable()->change();
            
            // Re-add the foreign key constraint with nullable support
            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('set null');
            
            // Update the submission_type enum to include public_submit
            $table->enum('submission_type', ['save', 'submit', 'auto_save', 'public_submit'])->default('submit')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            // Drop the nullable foreign key constraint
            $table->dropForeign(['submitted_by']);
            
            // Modify the column back to required
            $table->foreignId('submitted_by')->nullable(false)->change();
            
            // Re-add the original foreign key constraint
            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('cascade');
            
            // Revert the submission_type enum
            $table->enum('submission_type', ['save', 'submit', 'auto_save'])->default('submit')->change();
        });
    }
};
