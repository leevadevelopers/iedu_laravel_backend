<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('student_transport_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('fleet_bus_id');
            $table->unsignedBigInteger('bus_stop_id');
            $table->unsignedBigInteger('transport_route_id');
            $table->enum('event_type', ['check_in', 'check_out', 'no_show', 'early_exit']);
            $table->timestamp('event_timestamp');
            $table->string('validation_method', 20); // 'qr_code', 'rfid', 'manual', 'facial_recognition'
            $table->string('validation_data', 100)->nullable(); // QR code or RFID value
            $table->unsignedBigInteger('recorded_by'); // user who recorded (driver/assistant)
            $table->decimal('event_latitude', 10, 7)->nullable();
            $table->decimal('event_longitude', 10, 7)->nullable();
            $table->boolean('is_automated')->default(false);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable(); // additional context data
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('fleet_bus_id')->references('id')->on('fleet_buses')->onDelete('cascade');
            $table->foreign('bus_stop_id')->references('id')->on('bus_stops')->onDelete('cascade');
            $table->foreign('transport_route_id')->references('id')->on('transport_routes')->onDelete('cascade');
            $table->foreign('recorded_by')->references('id')->on('users')->onDelete('cascade');

            $table->index(['student_id', 'event_timestamp']);
            $table->index(['fleet_bus_id', 'event_timestamp']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('student_transport_events');
    }
};
