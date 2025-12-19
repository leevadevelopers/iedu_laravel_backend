<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the foreign key constraint first
        Schema::table('lesson_attendances', function (Blueprint $table) {
            $table->dropForeign(['lesson_id']);
        });
        
        // Make lesson_id nullable using raw SQL
        DB::statement('ALTER TABLE `lesson_attendances` MODIFY `lesson_id` BIGINT UNSIGNED NULL');
        
        // Re-add the foreign key constraint
        Schema::table('lesson_attendances', function (Blueprint $table) {
            $table->foreign('lesson_id')->references('id')->on('lessons')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the foreign key constraint
        Schema::table('lesson_attendances', function (Blueprint $table) {
            $table->dropForeign(['lesson_id']);
        });
        
        // Make lesson_id required again using raw SQL
        DB::statement('ALTER TABLE `lesson_attendances` MODIFY `lesson_id` BIGINT UNSIGNED NOT NULL');
        
        // Re-add the foreign key constraint
        Schema::table('lesson_attendances', function (Blueprint $table) {
            $table->foreign('lesson_id')->references('id')->on('lessons')->onDelete('cascade');
        });
    }
};

