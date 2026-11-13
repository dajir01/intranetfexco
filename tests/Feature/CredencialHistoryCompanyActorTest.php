<?php

namespace Tests\Feature;

use App\Http\Controllers\CredencialController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CredencialHistoryCompanyActorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('credenciales', function (Blueprint $table) {
            $table->unsignedInteger('id_credencial')->primary();
            $table->bigInteger('id_empresa');
            $table->unsignedInteger('id_expositor');
            $table->unsignedInteger('id_feria');
        });
        Schema::create('credencial_estado_historial', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_credencial');
            $table->string('accion');
            $table->string('tipo_anterior')->nullable();
            $table->string('tipo_nuevo')->nullable();
            $table->integer('estado_anterior')->nullable();
            $table->integer('estado_nuevo')->nullable();
            $table->integer('version_qr_anterior')->nullable();
            $table->integer('version_qr_nueva')->nullable();
            $table->text('motivo')->nullable();
            $table->text('observaciones')->nullable();
            $table->unsignedInteger('id_usuario')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('eventos', function (Blueprint $table) {
            $table->unsignedInteger('id_feria')->primary();
            $table->string('nombre_feria');
            $table->date('fecha_inicio')->nullable();
        });
        Schema::create('usuarios', function (Blueprint $table) {
            $table->unsignedInteger('id_usuario')->primary();
            $table->string('nombre_usuario')->nullable();
            $table->string('nick_usuario')->nullable();
        });
        Schema::create('empresas', function (Blueprint $table) {
            $table->bigInteger('id_empresa')->primary();
            $table->string('nombre_empresa')->nullable();
        });
        Schema::create('credencial_digital_descargas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_credencial');
            $table->unsignedInteger('id_usuario')->nullable();
            $table->bigInteger('id_empresa')->nullable();
            $table->text('usuario_empresa')->nullable();
            $table->string('tipo_descarga', 20);
            $table->timestamp('created_at');
        });

        DB::table('credenciales')->insert([
            'id_credencial' => 20,
            'id_empresa' => 36,
            'id_expositor' => 8,
            'id_feria' => 20,
        ]);
        DB::table('eventos')->insert([
            'id_feria' => 20,
            'nombre_feria' => 'Feria de prueba',
            'fecha_inicio' => '2026-11-13',
        ]);
    }

    public function test_download_history_displays_company_name_when_present(): void
    {
        DB::table('empresas')->insert([
            'id_empresa' => 36,
            'nombre_empresa' => 'Empresa de prueba',
        ]);
        DB::table('credencial_digital_descargas')->insert([
            'id_credencial' => 20,
            'id_usuario' => null,
            'id_empresa' => 36,
            'usuario_empresa' => 'empresa-prueba',
            'tipo_descarga' => 'png_individual',
            'created_at' => now(),
        ]);

        $response = app(CredencialController::class)->historialCredencial(
            Request::create('/credenciales/acreditados/20/historial', 'GET'),
            20
        );

        $this->assertSame(
            'Empresa de prueba',
            $response->getData(true)['eventos'][0]['acciones'][0]['usuario']
        );
        $this->assertSame(
            'Imagen digital individual descargada',
            $response->getData(true)['eventos'][0]['acciones'][0]['motivo']
        );
    }

    public function test_download_history_keeps_unavailable_label_when_user_and_company_are_missing(): void
    {
        DB::table('credencial_digital_descargas')->insert([
            'id_credencial' => 20,
            'id_usuario' => null,
            'id_empresa' => null,
            'usuario_empresa' => null,
            'tipo_descarga' => 'png_individual',
            'created_at' => now(),
        ]);

        $response = app(CredencialController::class)->historialCredencial(
            Request::create('/credenciales/acreditados/20/historial', 'GET'),
            20
        );

        $this->assertSame(
            'Usuario no disponible',
            $response->getData(true)['eventos'][0]['acciones'][0]['usuario']
        );
    }
}
