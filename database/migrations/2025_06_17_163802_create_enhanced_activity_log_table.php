<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

return new class extends Migration
{
    public function up()
    {
        Schema::table(config('activitylog.table_name'), function (Blueprint $table) {
            if (!Schema::hasColumn(config('activitylog.table_name'), 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('set null');
            }
            if (!Schema::hasColumn(config('activitylog.table_name'), 'event')) {
                $table->string('event')->nullable()->index();
            }
            if (!Schema::hasColumn(config('activitylog.table_name'), 'batch_uuid')) {
                $table->string('batch_uuid')->nullable()->index();
            }
            if (!Schema::hasColumn(config('activitylog.table_name'), 'ip_address')) {
                $table->ipAddress('ip_address')->nullable();
            }
            if (!Schema::hasColumn(config('activitylog.table_name'), 'user_agent')) {
                $table->text('user_agent')->nullable();
            }
            // Add new indexes if needed
            // $table->index(['tenant_id', 'log_name']);
            // $table->index(['tenant_id', 'created_at']);
            // $table->index(['subject_type', 'subject_id', 'tenant_id']);
        });
    }

    public function down()
    {
        Schema::table(config('activitylog.table_name'), function (Blueprint $table) {
            if (Schema::hasColumn(config('activitylog.table_name'), 'tenant_id')) {
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
            if (Schema::hasColumn(config('activitylog.table_name'), 'event')) {
                $table->dropColumn('event');
            }
            if (Schema::hasColumn(config('activitylog.table_name'), 'batch_uuid')) {
                $table->dropColumn('batch_uuid');
            }
            if (Schema::hasColumn(config('activitylog.table_name'), 'ip_address')) {
                $table->dropColumn('ip_address');
            }
            if (Schema::hasColumn(config('activitylog.table_name'), 'user_agent')) {
                $table->dropColumn('user_agent');
            }
            // Drop indexes if you added them above
        });
    }
};