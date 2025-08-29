#!/bin/bash

# Transport Module - Database Migrations Generator
echo "🗄️ Creating Transport Module Migrations..."

# 1. Transport Routes Migration
cat > database/migrations/$(date +%Y_%m_%d_%H%M%S)_create_transport_routes_table.php << 'EOF'
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

            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->index(['school_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('transport_routes');
    }
};
EOF

# 2. Bus Stops Migration
cat > database/migrations/$(date +%Y_%m_%d_%H%M%S)_create_bus_stops_table.php << 'EOF'
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
EOF

# 3. Fleet (Buses) Migration
cat > database/migrations/$(date +%Y_%m_%d_%H%M%S)_create_fleet_buses_table.php << 'EOF'
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

            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->unique(['school_id', 'internal_code']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('fleet_buses');
    }
};
EOF

# 4. Bus Route Assignments
cat > database/migrations/$(date +%Y_%m_%d_%H%M%S)_create_bus_route_assignments_table.php << 'EOF'
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
EOF

# 5. Student Transport Subscriptions
cat > database/migrations/$(date +%Y_%m_%d_%H%M%S)_create_student_transport_subscriptions_table.php << 'EOF'
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
EOF

# 6. Transport Tracking (Real-time GPS)
cat > database/migrations/$(date +%Y_%m_%d_%H%M%S)_create_transport_tracking_table.php << 'EOF'
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
EOF

# 7. Student Transport Events (Check-in/Check-out)
cat > database/migrations/$(date +%Y_%m_%d_%H%M%S)_create_student_transport_events_table.php << 'EOF'
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
EOF

# 8. Transport Incidents
cat > database/migrations/$(date +%Y_%m_%d_%H%M%S)_create_transport_incidents_table.php << 'EOF'
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
EOF

# 9. Daily Transport Logs
cat > database/migrations/$(date +%Y_%m_%d_%H%M%S)_create_transport_daily_logs_table.php << 'EOF'
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
EOF

# 10. Parent Notifications Log
cat > database/migrations/$(date +%Y_%m_%d_%H%M%S)_create_transport_notifications_table.php << 'EOF'
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
EOF

echo "✅ Transport module migrations created successfully!"
echo "📝 Run: php artisan migrate --path=database/migrations/transport"
