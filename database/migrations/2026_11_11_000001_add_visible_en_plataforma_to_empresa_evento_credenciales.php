<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('empresa_evento_credenciales') || Schema::hasColumn('empresa_evento_credenciales', 'visible_en_plataforma')) {
            return;
        }

        Schema::table('empresa_evento_credenciales', function (Blueprint $table): void {
            $table->boolean('visible_en_plataforma')->default(false)->after('estado');
        });

        DB::table('empresa_evento_credenciales')->update(['visible_en_plataforma' => true]);
    }

    public function down(): void
    {
        if (Schema::hasTable('empresa_evento_credenciales') && Schema::hasColumn('empresa_evento_credenciales', 'visible_en_plataforma')) {
            Schema::table('empresa_evento_credenciales', function (Blueprint $table): void {
                $table->dropColumn('visible_en_plataforma');
            });
        }
    }
};
