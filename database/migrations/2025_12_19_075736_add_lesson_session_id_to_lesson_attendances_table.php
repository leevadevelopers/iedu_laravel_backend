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
        Schema::table('lesson_attendances', function (Blueprint $table) {
            $table->foreignId('lesson_session_id')->nullable()->after('lesson_id')->constrained('lesson_sessions')->onDelete('cascade');
            $table->index('lesson_session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lesson_attendances', function (Blueprint $table) {
            $table->dropForeign(['lesson_session_id']);
            $table->dropIndex(['lesson_session_id']);
            $table->dropColumn('lesson_session_id');
        });
    }
};
