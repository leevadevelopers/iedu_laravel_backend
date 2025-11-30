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
        if (!Schema::hasColumn('authors', 'tenant_id')) {
            Schema::table('authors', function (Blueprint $table) {
                $table->foreignId('tenant_id')->after('id')->constrained()->cascadeOnDelete();
                $table->index('tenant_id');
            });
        } else {
            // Column already exists, just ensure foreign key and index exist
            // Try to add foreign key if it doesn't exist
            try {
                Schema::table('authors', function (Blueprint $table) {
                    $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                });
            } catch (\Exception $e) {
                // Foreign key might already exist, ignore
            }

            // Try to add index if it doesn't exist
            try {
                Schema::table('authors', function (Blueprint $table) {
                    $table->index('tenant_id');
                });
            } catch (\Exception $e) {
                // Index might already exist, ignore
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('authors', 'tenant_id')) {
            Schema::table('authors', function (Blueprint $table) {
                // Try to drop foreign key if it exists
                try {
                    $table->dropForeign(['tenant_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist, ignore
                }

                // Try to drop index if it exists
                try {
                    $table->dropIndex(['tenant_id']);
                } catch (\Exception $e) {
                    // Index might not exist, ignore
                }

                $table->dropColumn('tenant_id');
            });
        }
    }
};
