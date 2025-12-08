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
        if (Schema::hasTable('assessment_types') && !Schema::hasColumn('assessment_types', 'max_score')) {
            Schema::table('assessment_types', function (Blueprint $table) {
                $table->decimal('max_score', 5, 2)->nullable()->after('default_weight');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('assessment_types') && Schema::hasColumn('assessment_types', 'max_score')) {
            Schema::table('assessment_types', function (Blueprint $table) {
                $table->dropColumn('max_score');
            });
        }
    }
};

