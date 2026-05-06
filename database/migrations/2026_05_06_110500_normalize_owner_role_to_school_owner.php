<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $ownerRole = DB::table('roles')->where('name', 'owner')->first();
        $schoolOwnerRole = DB::table('roles')->where('name', 'school_owner')->first();

        if ($ownerRole && !$schoolOwnerRole) {
            DB::table('roles')
                ->where('id', $ownerRole->id)
                ->update([
                    'name' => 'school_owner',
                    'display_name' => 'Dono da Escola',
                    'updated_at' => now(),
                ]);
            return;
        }

        if ($ownerRole && $schoolOwnerRole) {
            DB::table('tenant_users')
                ->where('role_id', $ownerRole->id)
                ->update(['role_id' => $schoolOwnerRole->id]);

            DB::table('model_has_roles')
                ->where('role_id', $ownerRole->id)
                ->update(['role_id' => $schoolOwnerRole->id]);

            DB::table('roles')->where('id', $ownerRole->id)->delete();
        }
    }

    public function down(): void
    {
        $ownerRole = DB::table('roles')->where('name', 'owner')->first();
        $schoolOwnerRole = DB::table('roles')->where('name', 'school_owner')->first();

        if (!$ownerRole && $schoolOwnerRole) {
            DB::table('roles')
                ->where('id', $schoolOwnerRole->id)
                ->update([
                    'name' => 'owner',
                    'display_name' => 'Owner',
                    'updated_at' => now(),
                ]);
        }
    }
};

