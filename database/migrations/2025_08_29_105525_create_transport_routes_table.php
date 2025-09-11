<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('transport_routes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->text('description')->nullable();
            $table->json('waypoints'); // GPS coordinates for route path
            $table->time('departure_time');
            $table->time('arrival_time');
            $table->integer('estimated_duration_minutes');
            $table->decimal('total_distance_km', 8, 2);
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
            $table->enum('shift', ['morning', 'afternoon', 'both'])->default('morning');
            $table->json('operating_days')->default('["monday","tuesday","wednesday","thursday","friday"]');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->index(['school_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('transport_routes');
    }
};
