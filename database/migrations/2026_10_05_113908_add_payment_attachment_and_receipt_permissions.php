<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $timestamp = now();

        DB::table('permissions')->insertOrIgnore([
            [
                'name' => 'pagos.ver_comprobantes',
                'module' => 'pagos',
                'action' => 'ver_comprobantes',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'pagos.ver_recibos',
                'module' => 'pagos',
                'action' => 'ver_recibos',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permissions')
            ->whereIn('name', ['pagos.ver_comprobantes', 'pagos.ver_recibos'])
            ->delete();
    }
};
