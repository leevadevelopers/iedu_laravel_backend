<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Permite que tenant_id seja NULL para superadmin que não pertence a nenhum tenant
     *
     * IMPORTANTE: MySQL não permite NULL em campos de PRIMARY KEY.
     * Solução: Remover tenant_id da PRIMARY KEY e criar uma PRIMARY KEY alternativa
     * sem tenant_id, mantendo um índice único para garantir unicidade.
     */
    public function up(): void
    {
        // Remove a primary key atual
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropPrimary('model_has_roles_role_model_type_primary');
        });

        // Altera tenant_id para nullable usando SQL direto
        DB::statement('ALTER TABLE `model_has_roles` MODIFY `tenant_id` BIGINT UNSIGNED NULL');

        // Cria uma nova PRIMARY KEY sem tenant_id (MySQL não permite NULL em PRIMARY KEY)
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        // Cria um índice único que inclui tenant_id para garantir unicidade
        // Nota: Em índices únicos do MySQL, múltiplos NULLs são permitidos
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->unique(['tenant_id', 'role_id', 'model_id', 'model_type'], 'model_has_roles_tenant_role_model_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove o índice único apenas se existir (usando SQL direto para evitar erros)
        try {
            DB::statement('ALTER TABLE `model_has_roles` DROP INDEX `model_has_roles_tenant_role_model_unique`');
        } catch (\Exception $e) {
            // Índice não existe, ignora o erro
        }

        // Remove a primary key atual
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropPrimary('model_has_roles_role_model_type_primary');
        });

        // Altera tenant_id para NOT NULL usando SQL direto
        // Primeiro, precisamos garantir que não há registros com tenant_id NULL
        DB::statement('UPDATE `model_has_roles` SET `tenant_id` = 0 WHERE `tenant_id` IS NULL');
        DB::statement('ALTER TABLE `model_has_roles` MODIFY `tenant_id` BIGINT UNSIGNED NOT NULL');

        // Recria a primary key original com tenant_id
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->primary(['tenant_id', 'role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });
    }
};
