<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('transport_incidents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('fleet_bus_id');
            $table->unsignedBigInteger('transport_route_id')->nullable();
            $table->enum('incident_type', ['breakdown', 'accident', 'delay', 'behavioral', 'medical', 'other']);
            $table->enum('severity', ['low', 'medium', 'high', 'critical']);
            $table->string('title');
            $table->text('description');
            $table->timestamp('incident_datetime');
            $table->decimal('incident_latitude', 10, 7)->nullable();
            $table->decimal('incident_longitude', 10, 7)->nullable();
            $table->unsignedBigInteger('reported_by'); // user who reported
            $table->json('affected_students')->nullable(); // array of student IDs
            $table->json('witnesses')->nullable(); // staff/parent contact info
            $table->text('immediate_action_taken')->nullable();
            $table->enum('status', ['reported', 'investigating', 'resolved', 'closed'])->default('reported');
            $table->unsignedBigInteger('assigned_to')->nullable(); // user handling the incident
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->json('attachments')->nullable(); // photos, documents
            $table->boolean('parents_notified')->default(false);
            $table->timestamp('parents_notified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->foreign('fleet_bus_id')->references('id')->on('fleet_buses')->onDelete('cascade');
            $table->foreign('transport_route_id')->references('id')->on('transport_routes')->onDelete('set null');
            $table->foreign('reported_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');

            $table->index(['school_id', 'status']);
            $table->index(['fleet_bus_id', 'incident_datetime']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('transport_incidents');
    }
};
