<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // First, temporarily change to VARCHAR to avoid ENUM validation issues
        DB::statement("ALTER TABLE `school_users` MODIFY `role` VARCHAR(50) NOT NULL DEFAULT 'staff'");
        
        // Now change back to ENUM with all values including 'student'
        DB::statement("ALTER TABLE `school_users` MODIFY `role` ENUM('teacher','admin','staff','principal','counselor','nurse','librarian','coach','volunteer','student') NOT NULL DEFAULT 'staff'");
    }

    public function down(): void
    {
        // First, temporarily change to VARCHAR
        DB::statement("ALTER TABLE `school_users` MODIFY `role` VARCHAR(50) NOT NULL DEFAULT 'staff'");
        
        // Update any 'student' roles to 'staff' before removing from enum
        DB::table('school_users')->where('role', 'student')->update(['role' => 'staff']);
        
        // Revert by removing 'student' from enum
        DB::statement("ALTER TABLE `school_users` MODIFY `role` ENUM('teacher','admin','staff','principal','counselor','nurse','librarian','coach','volunteer') NOT NULL DEFAULT 'staff'");
    }
};


