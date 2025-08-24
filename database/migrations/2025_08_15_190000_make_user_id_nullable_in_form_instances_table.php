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
            // Drop the existing foreign key constraint
            $table->dropForeign(['user_id']);
            
            // Modify the column to be nullable
            $table->foreignId('user_id')->nullable()->change();
            
            // Re-add the foreign key constraint with nullable support
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_instances', function (Blueprint $table) {
            // Drop the nullable foreign key constraint
            $table->dropForeign(['user_id']);
            
            // Modify the column back to required
            $table->foreignId('user_id')->nullable(false)->change();
            
            // Re-add the original foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
