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
        if (!Schema::hasColumn('book_files', 'tenant_id')) {
            Schema::table('book_files', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->after('book_id')->constrained()->cascadeOnDelete();
                $table->index('tenant_id');
            });
        } else {
            // Column already exists, just ensure foreign key and index exist
            try {
                Schema::table('book_files', function (Blueprint $table) {
                    $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                });
            } catch (\Exception $e) {
                // Foreign key might already exist, ignore
            }

            try {
                Schema::table('book_files', function (Blueprint $table) {
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
        if (Schema::hasColumn('book_files', 'tenant_id')) {
            Schema::table('book_files', function (Blueprint $table) {
                try {
                    $table->dropForeign(['tenant_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist, ignore
                }

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
