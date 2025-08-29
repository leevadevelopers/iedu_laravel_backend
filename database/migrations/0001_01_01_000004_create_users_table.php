<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('identifier')->unique();
            $table->foreignId('role_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('type', ['email', 'phone']);
            $table->timestamp('verified_at')->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->json('settings')->nullable();
            $table->string('password');
            $table->boolean('must_change')->default(false);
               // Educational Role
               $table->enum('user_type', [
                'student', 'parent', 'teacher', 'admin', 'staff',
                'principal', 'counselor', 'nurse'
            ]);

            // Contact Information
            $table->string('phone', 20)->nullable();
            $table->json('emergency_contact_json')->nullable();
            $table->json('transport_notification_preferences')->nullable();
            $table->string('whatsapp_phone', 20)->nullable();

            $table->softDeletes();
            $table->rememberToken();
            $table->timestamps();


            $table->index(['is_active', 'created_at']);
            $table->index('last_login_at');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('identifier')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
