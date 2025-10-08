<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Extend enum to include 'student'
        DB::statement("ALTER TABLE `school_users` MODIFY `role` ENUM('teacher','admin','staff','principal','counselor','nurse','librarian','coach','volunteer','student') NOT NULL DEFAULT 'staff'");
    }

    public function down(): void
    {
        // Revert by removing 'student' from enum
        DB::statement("ALTER TABLE `school_users` MODIFY `role` ENUM('teacher','admin','staff','principal','counselor','nurse','librarian','coach','volunteer') NOT NULL DEFAULT 'staff'");
    }
};


