<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('bus_route_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('fleet_bus_id');
            $table->unsignedBigInteger('transport_route_id');
            $table->unsignedBigInteger('driver_id'); // users table
            $table->unsignedBigInteger('assistant_id')->nullable(); // users table
            $table->date('assigned_date');
            $table->date('valid_until')->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->foreign('fleet_bus_id')->references('id')->on('fleet_buses')->onDelete('cascade');
            $table->foreign('transport_route_id')->references('id')->on('transport_routes')->onDelete('cascade');
            $table->foreign('driver_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('assistant_id')->references('id')->on('users')->onDelete('set null');

            // Ensure one active assignment per bus per route per day
            $table->unique(['fleet_bus_id', 'transport_route_id', 'assigned_date'], 'unique_bus_route_assignment');
        });
    }

    public function down()
    {
        Schema::dropIfExists('bus_route_assignments');
    }
};
