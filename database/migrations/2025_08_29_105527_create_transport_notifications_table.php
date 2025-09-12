<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('transport_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('parent_id'); // users table
            $table->enum('notification_type', ['check_in', 'check_out', 'delay', 'incident', 'route_change', 'general']);
            $table->enum('channel', ['email', 'sms', 'push', 'whatsapp']);
            $table->string('subject');
            $table->text('message');
            $table->json('metadata')->nullable(); // additional context
            $table->enum('status', ['pending', 'sent', 'delivered', 'failed', 'read'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('users')->onDelete('cascade');

            $table->index(['parent_id', 'status']);
            $table->index(['student_id', 'notification_type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('transport_notifications');
    }
};
