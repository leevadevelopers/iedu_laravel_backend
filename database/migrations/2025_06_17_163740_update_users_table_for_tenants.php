<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_photo_path')->nullable()->after('verified_at');
            $table->boolean('is_active')->default(true)->after('profile_photo_path');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->json('settings')->nullable()->after('last_login_at');
            $table->softDeletes();
            
            $table->index(['is_active', 'created_at']);
            $table->index('last_login_at');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'profile_photo_path',
                'is_active', 
                'last_login_at',
                'settings'
            ]);
            $table->dropSoftDeletes();
        });
    }
};