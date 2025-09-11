<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('transport_daily_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('fleet_bus_id');
            $table->unsignedBigInteger('transport_route_id');
            $table->unsignedBigInteger('driver_id');
            $table->unsignedBigInteger('assistant_id')->nullable();
            $table->date('log_date');
            $table->enum('shift', ['morning', 'afternoon']);
            $table->time('departure_time')->nullable();
            $table->time('arrival_time')->nullable();
            $table->integer('students_picked_up')->default(0);
            $table->integer('students_dropped_off')->default(0);
            $table->decimal('fuel_level_start', 5, 2)->nullable();
            $table->decimal('fuel_level_end', 5, 2)->nullable();
            $table->integer('odometer_start')->nullable();
            $table->integer('odometer_end')->nullable();
            $table->json('safety_checklist')->nullable(); // pre-departure checks
            $table->text('notes')->nullable();
            $table->enum('status', ['completed', 'cancelled', 'partial'])->default('completed');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->foreign('fleet_bus_id')->references('id')->on('fleet_buses')->onDelete('cascade');
            $table->foreign('transport_route_id')->references('id')->on('transport_routes')->onDelete('cascade');
            $table->foreign('driver_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('assistant_id')->references('id')->on('users')->onDelete('set null');

            $table->unique(['fleet_bus_id', 'transport_route_id', 'log_date', 'shift'], 'unique_daily_log');
        });
    }

    public function down()
    {
        Schema::dropIfExists('transport_daily_logs');
    }
};
