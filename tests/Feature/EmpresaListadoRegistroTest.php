<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmpresaListadoRegistroTest extends TestCase
{
    private string $previousDatabaseDefault;

    private mixed $previousSqliteDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousDatabaseDefault = config('database.default');
        $this->previousSqliteDatabase = config('database.connections.sqlite.database');
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('empresas', function (Blueprint $table): void {
            $table->increments('id_empresa');
            $table->string('nombre_empresa');
            $table->string('nit')->nullable();
            $table->string('nombre_gerente')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('pais')->nullable();
            $table->boolean('es_activo')->default(true);
            $table->dateTime('fecha_creacion')->nullable();
            $table->dateTime('fecha_actualizacion_empresa')->nullable();
            $table->unsignedInteger('id_usuario_creacion')->nullable();
            $table->unsignedInteger('id_usuario_actualizacion')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        Schema::create('usuarios', function (Blueprint $table): void {
            $table->increments('id_usuario');
            $table->string('nombre_usuario');
        });
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        Config::set('database.default', $this->previousDatabaseDefault);
        Config::set('database.connections.sqlite.database', $this->previousSqliteDatabase);

        parent::tearDown();
    }

    public function test_index_returns_company_audit_dates_instead_of_eloquent_timestamps(): void
    {
        DB::table('usuarios')->insert([
            ['id_usuario' => 1, 'nombre_usuario' => 'Creador'],
            ['id_usuario' => 2, 'nombre_usuario' => 'Editor'],
        ]);
        DB::table('empresas')->insert([
            'nombre_empresa' => 'Empresa de prueba',
            'fecha_creacion' => '2025-01-10 09:00:00',
            'fecha_actualizacion_empresa' => '2025-03-15 14:30:00',
            'id_usuario_creacion' => 1,
            'id_usuario_actualizacion' => 2,
            'created_at' => '2025-03-15 14:30:00',
            'updated_at' => '2025-03-15 14:30:00',
        ]);

        $this->withoutMiddleware()
            ->getJson('/api/empresas?sort_by=fecha_creacion')
            ->assertOk()
            ->assertJsonPath('data.0.fecha_creacion', '2025-01-10 09:00:00')
            ->assertJsonPath('data.0.fecha_actualizacion_empresa', '2025-03-15 14:30:00')
            ->assertJsonPath('data.0.usuario_creacion_nombre', 'Creador')
            ->assertJsonPath('data.0.usuario_actualizacion_nombre', 'Editor')
            ->assertJsonMissingPath('data.0.created_at')
            ->assertJsonMissingPath('data.0.updated_at');
    }
}
