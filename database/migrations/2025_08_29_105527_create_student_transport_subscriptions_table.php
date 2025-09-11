<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('student_transport_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('pickup_stop_id'); // bus_stops
            $table->unsignedBigInteger('dropoff_stop_id'); // bus_stops
            $table->unsignedBigInteger('transport_route_id');
            $table->string('qr_code', 100)->unique();
            $table->string('rfid_card_id', 50)->nullable();
            $table->enum('subscription_type', ['daily', 'weekly', 'monthly', 'term'])->default('monthly');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('monthly_fee', 8, 2)->default(0);
            $table->boolean('auto_renewal')->default(true);
            $table->json('authorized_parents')->nullable(); // parent user IDs who can track
            $table->enum('status', ['active', 'suspended', 'cancelled', 'pending_approval'])->default('pending_approval');
            $table->text('special_needs')->nullable(); // wheelchair, medication, etc.
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('pickup_stop_id')->references('id')->on('bus_stops')->onDelete('cascade');
            $table->foreign('dropoff_stop_id')->references('id')->on('bus_stops')->onDelete('cascade');
            $table->foreign('transport_route_id')->references('id')->on('transport_routes')->onDelete('cascade');

            // Prevent duplicate active subscriptions
            $table->unique(['student_id', 'transport_route_id', 'status'], 'unique_active_subscription');
        });
    }

    public function down()
    {
        Schema::dropIfExists('student_transport_subscriptions');
    }
};
