<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('bus_stops', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('transport_route_id');
            $table->string('name');
            $table->string('code', 20);
            $table->text('address');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->integer('stop_order');
            $table->time('scheduled_arrival_time');
            $table->time('scheduled_departure_time');
            $table->integer('estimated_wait_minutes')->default(2);
            $table->boolean('is_pickup_point')->default(true);
            $table->boolean('is_dropoff_point')->default(true);
            $table->json('landmarks')->nullable(); // nearby landmarks for easy identification
            $table->enum('status', ['active', 'inactive', 'temporary'])->default('active');
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->foreign('transport_route_id')->references('id')->on('transport_routes')->onDelete('cascade');
            $table->unique(['school_id', 'code']);
            $table->index(['transport_route_id', 'stop_order']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('bus_stops');
    }
};
