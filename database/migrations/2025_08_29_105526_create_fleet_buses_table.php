<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('fleet_buses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->string('license_plate', 20)->unique();
            $table->string('internal_code', 20);
            $table->string('make'); // Mercedes, Volvo, etc.
            $table->string('model');
            $table->year('manufacture_year');
            $table->integer('capacity');
            $table->integer('current_capacity')->default(0);
            $table->enum('fuel_type', ['diesel', 'petrol', 'electric', 'hybrid'])->default('diesel');
            $table->decimal('fuel_consumption_per_km', 5, 2)->nullable();
            $table->string('gps_device_id')->nullable();
            $table->json('safety_features')->nullable(); // seat belts, GPS, cameras, etc.
            $table->date('last_inspection_date')->nullable();
            $table->date('next_inspection_due')->nullable();
            $table->date('insurance_expiry')->nullable();
            $table->enum('status', ['active', 'maintenance', 'out_of_service', 'retired'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->unique(['school_id', 'internal_code']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('fleet_buses');
    }
};
