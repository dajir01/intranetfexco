<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSION = 'credenciales.empresas.plataforma';

    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        DB::table('permissions')->updateOrInsert(
            ['name' => self::PERMISSION],
            ['module' => 'credenciales.empresas', 'action' => 'plataforma', 'created_at' => now(), 'updated_at' => now()],
        );

        if (! Schema::hasTable('user_permissions')) {
            return;
        }

        $permissionId = DB::table('permissions')->where('name', self::PERMISSION)->value('id');
        $sourceId = DB::table('permissions')->where('name', 'credenciales.empresas.crear')->value('id');
        if (! $permissionId || ! $sourceId) {
            return;
        }

        DB::table('user_permissions')
            ->where('permission_id', $sourceId)
            ->pluck('user_id')
            ->each(fn ($userId) => DB::table('user_permissions')->insertOrIgnore([
                'user_id' => $userId,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $permissionId = DB::table('permissions')->where('name', self::PERMISSION)->value('id');
        if (Schema::hasTable('user_permissions') && $permissionId) {
            DB::table('user_permissions')->where('permission_id', $permissionId)->delete();
        }

        DB::table('permissions')->where('name', self::PERMISSION)->delete();
    }
};
