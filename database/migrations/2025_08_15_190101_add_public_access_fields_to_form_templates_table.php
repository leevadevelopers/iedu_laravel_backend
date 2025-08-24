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
        Schema::table('form_templates', function (Blueprint $table) {
            $table->string('public_access_token', 64)->nullable()->unique()->after('created_by');
            $table->boolean('public_access_enabled')->default(false)->after('public_access_token');
            $table->timestamp('public_access_expires_at')->nullable()->after('public_access_enabled');
            $table->boolean('allow_multiple_submissions')->default(true)->after('public_access_expires_at');
            $table->integer('max_submissions')->nullable()->after('allow_multiple_submissions');
            $table->json('public_access_settings')->nullable()->after('max_submissions');
            
            // Add index for faster token lookups
            $table->index(['public_access_token', 'public_access_enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_templates', function (Blueprint $table) {
            $table->dropIndex(['public_access_token', 'public_access_enabled']);
            $table->dropColumn([
                'public_access_token', 
                'public_access_enabled', 
                'public_access_expires_at',
                'allow_multiple_submissions',
                'max_submissions',
                'public_access_settings'
            ]);
        });
    }
};
