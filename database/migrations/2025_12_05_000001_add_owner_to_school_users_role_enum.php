<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, temporarily change to VARCHAR to avoid ENUM validation issues
        DB::statement("ALTER TABLE `school_users` MODIFY `role` VARCHAR(50) NOT NULL DEFAULT 'staff'");
        
        // Now change back to ENUM with all values including 'owner'
        DB::statement("ALTER TABLE `school_users` MODIFY `role` ENUM('owner','admin','teacher','staff','principal','counselor','nurse','librarian','coach','volunteer','student') NOT NULL DEFAULT 'staff'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First, temporarily change to VARCHAR
        DB::statement("ALTER TABLE `school_users` MODIFY `role` VARCHAR(50) NOT NULL DEFAULT 'staff'");
        
        // Update any 'owner' roles to 'admin' before removing from enum
        DB::table('school_users')->where('role', 'owner')->update(['role' => 'admin']);
        
        // Revert by removing 'owner' from enum
        DB::statement("ALTER TABLE `school_users` MODIFY `role` ENUM('teacher','admin','staff','principal','counselor','nurse','librarian','coach','volunteer','student') NOT NULL DEFAULT 'staff'");
    }
};

