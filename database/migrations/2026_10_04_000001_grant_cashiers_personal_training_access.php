<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $cashierId = DB::table('roles')->where('name', 'cashier')->value('id');
        if (! $cashierId) {
            return;
        }

        DB::table('permissions')->insertOrIgnore([
            'name' => 'pt.manage',
            'label' => 'Manage personal training',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $permissionId = DB::table('permissions')->where('name', 'pt.manage')->value('id');
        DB::table('permission_role')->insertOrIgnore([
            'role_id' => $cashierId,
            'permission_id' => $permissionId,
        ]);
    }

    public function down(): void
    {
        $cashierId = DB::table('roles')->where('name', 'cashier')->value('id');
        $permissionId = DB::table('permissions')->where('name', 'pt.manage')->value('id');
        if ($cashierId && $permissionId) {
            DB::table('permission_role')->where('role_id', $cashierId)->where('permission_id', $permissionId)->delete();
        }
    }
};
