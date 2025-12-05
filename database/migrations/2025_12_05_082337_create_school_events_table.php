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
        Schema::create('school_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('event_type', ['academic', 'holiday', 'activity', 'meeting', 'exam', 'other'])->default('other');
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->boolean('all_day')->default(false);
            $table->string('location')->nullable();
            $table->string('color', 7)->nullable()->default('#3b82f6'); // Hex color code
            $table->timestamps();

            // Indexes
            $table->index('school_id');
            $table->index('event_type');
            $table->index('start_date');
            $table->index('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_events');
    }
};
