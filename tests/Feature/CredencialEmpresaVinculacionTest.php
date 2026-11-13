<?php

namespace Tests\Feature;

use App\Http\Controllers\CredencialController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CredencialEmpresaVinculacionTest extends TestCase
{
    private string $previousDatabaseDefault;

    private string $previousSqliteDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousDatabaseDefault = config('database.default');
        $this->previousSqliteDatabase = config('database.connections.sqlite.database');
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('empresa_evento_credenciales', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_empresa');
            $table->unsignedInteger('id_feria');
            $table->string('tipo_credencial');
            $table->unsignedTinyInteger('tipo_credenciales')->nullable();
            $table->unsignedInteger('cantidad_credenciales')->default(0);
            $table->unsignedInteger('id_contrato')->nullable();
            $table->boolean('estado')->default(true);
            $table->boolean('visible_en_plataforma')->default(true);
            $table->unsignedInteger('id_usuario_creacion')->nullable();
            $table->timestamps();
        });

        Schema::create('eventos', function (Blueprint $table): void {
            $table->increments('id_feria');
            $table->string('tipo_credenciales')->nullable();
        });

        Schema::create('empresas', function (Blueprint $table): void {
            $table->increments('id_empresa');
            $table->string('nombre_empresa');
        });

        Schema::create('credenciales', function (Blueprint $table): void {
            $table->increments('id_credencial');
            $table->unsignedInteger('id_empresa');
            $table->unsignedInteger('id_feria');
            $table->unsignedInteger('tipo_credencial');
            $table->unsignedInteger('id_contrato')->nullable();
            $table->unsignedInteger('estado_credencial');
        });
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        Config::set('database.connections.sqlite.database', $this->previousSqliteDatabase);
        Config::set('database.default', $this->previousDatabaseDefault);

        parent::tearDown();
    }

    public function test_direct_link_can_be_removed_when_its_only_credentials_were_deleted(): void
    {
        $this->crearVinculacion();
        DB::table('credenciales')->insert([
            'id_empresa' => 7,
            'id_feria' => 19,
            'tipo_credencial' => 1,
            'id_contrato' => null,
            'estado_credencial' => 3,
        ]);

        $response = (new CredencialController)->destroyEmpresaVinculacion(42);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseMissing('empresa_evento_credenciales', ['id' => 42]);
        $this->assertDatabaseHas('credenciales', ['estado_credencial' => 3]);
    }

    public function test_direct_link_cannot_be_removed_while_non_deleted_credentials_remain(): void
    {
        $this->crearVinculacion();
        DB::table('credenciales')->insert([
            ['id_empresa' => 7, 'id_feria' => 19, 'tipo_credencial' => 1, 'id_contrato' => null, 'estado_credencial' => 0],
            ['id_empresa' => 7, 'id_feria' => 19, 'tipo_credencial' => 1, 'id_contrato' => null, 'estado_credencial' => 1],
            ['id_empresa' => 7, 'id_feria' => 19, 'tipo_credencial' => 1, 'id_contrato' => null, 'estado_credencial' => 2],
        ]);

        $response = (new CredencialController)->destroyEmpresaVinculacion(42);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertDatabaseHas('empresa_evento_credenciales', ['id' => 42]);
    }

    public function test_contract_link_cannot_be_removed_through_the_direct_link_action(): void
    {
        $this->crearVinculacion(idContrato: 84);

        $response = (new CredencialController)->destroyEmpresaVinculacion(42);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(
            'Solo se pueden eliminar vinculaciones directas sin contrato.',
            $response->getData(true)['message'],
        );
        $this->assertDatabaseHas('empresa_evento_credenciales', ['id' => 42]);
    }

    public function test_platform_visibility_can_be_changed_for_a_direct_link(): void
    {
        $this->crearVinculacion();

        $response = (new CredencialController)->actualizarVisibilidadPlataforma(
            Request::create('/', 'PATCH', ['visible_en_plataforma' => false]),
            42,
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['data']['visible_en_plataforma']);
        $this->assertDatabaseHas('empresa_evento_credenciales', [
            'id' => 42,
            'visible_en_plataforma' => false,
        ]);
    }

    public function test_platform_visibility_cannot_be_changed_for_a_contract_link(): void
    {
        $this->crearVinculacion(idContrato: 84);

        $response = (new CredencialController)->actualizarVisibilidadPlataforma(
            Request::create('/', 'PATCH', ['visible_en_plataforma' => false]),
            42,
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertTrue((bool) DB::table('empresa_evento_credenciales')->where('id', 42)->value('visible_en_plataforma'));
    }

    public function test_new_direct_link_starts_hidden_from_platform(): void
    {
        DB::table('eventos')->insert([
            'id_feria' => 19,
            'tipo_credenciales' => 'Expositor',
        ]);
        DB::table('empresas')->insert([
            'id_empresa' => 7,
            'nombre_empresa' => 'Empresa de prueba',
        ]);

        $response = (new CredencialController)->storeEmpresa(Request::create('/', 'POST', [
            'id_feria' => 19,
            'id_empresa' => 7,
            'nombre_empresa' => 'Empresa de prueba',
            'tipo_credencial' => 'Expositor',
            'tipo_credenciales' => 0,
            'cantidad_credenciales' => 2,
        ]));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertFalse((bool) DB::table('empresa_evento_credenciales')->value('visible_en_plataforma'));
    }

    public function test_visibility_migration_preserves_existing_links_and_hides_future_links(): void
    {
        Schema::drop('empresa_evento_credenciales');
        Schema::create('empresa_evento_credenciales', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_empresa');
            $table->unsignedInteger('id_feria');
            $table->boolean('estado')->default(true);
        });
        DB::table('empresa_evento_credenciales')->insert([
            'id_empresa' => 7,
            'id_feria' => 19,
            'estado' => true,
        ]);

        $migration = require database_path('migrations/2026_11_11_000001_add_visible_en_plataforma_to_empresa_evento_credenciales.php');
        $migration->up();

        $this->assertDatabaseHas('empresa_evento_credenciales', [
            'id' => 1,
            'visible_en_plataforma' => true,
        ]);

        DB::table('empresa_evento_credenciales')->insert([
            'id_empresa' => 8,
            'id_feria' => 19,
            'estado' => true,
        ]);

        $this->assertDatabaseHas('empresa_evento_credenciales', [
            'id' => 2,
            'visible_en_plataforma' => false,
        ]);
    }

    private function crearVinculacion(?int $idContrato = null): void
    {
        DB::table('empresa_evento_credenciales')->insert([
            'id' => 42,
            'id_empresa' => 7,
            'id_feria' => 19,
            'tipo_credencial' => 'Expositor',
            'id_contrato' => $idContrato,
        ]);
    }
}
