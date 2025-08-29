<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('transport_tracking', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('fleet_bus_id');
            $table->unsignedBigInteger('transport_route_id');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('speed_kmh', 5, 2)->default(0);
            $table->integer('heading')->nullable(); // compass direction
            $table->decimal('altitude', 8, 2)->nullable();
            $table->timestamp('tracked_at');
            $table->string('status', 50)->default('in_transit'); // departed, in_transit, at_stop, arrived
            $table->integer('current_stop_id')->nullable();
            $table->integer('next_stop_id')->nullable();
            $table->integer('eta_minutes')->nullable(); // estimated time to next stop
            $table->json('raw_gps_data')->nullable(); // store raw GPS payload
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->foreign('fleet_bus_id')->references('id')->on('fleet_buses')->onDelete('cascade');
            $table->foreign('transport_route_id')->references('id')->on('transport_routes')->onDelete('cascade');

            $table->index(['fleet_bus_id', 'tracked_at']);
            $table->index(['transport_route_id', 'tracked_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('transport_tracking');
    }
};
