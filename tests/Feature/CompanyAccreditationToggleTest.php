<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CompanyAccreditationToggleTest extends TestCase
{
    private string $previousDatabaseDefault;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousDatabaseDefault = config('database.default');
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('usuarios', function (Blueprint $table): void {
            $table->increments('id_usuario');
            $table->string('nick_usuario')->unique();
            $table->string('nombre_usuario')->nullable();
            $table->string('pass_usuario')->nullable();
            $table->boolean('estado')->nullable();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->unique();
            $table->string('module');
            $table->string('action');
            $table->timestamps();
        });

        Schema::create('user_permissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('permission_id');
            $table->timestamps();
            $table->unique(['user_id', 'permission_id']);
        });

        Schema::create('eventos', function (Blueprint $table): void {
            $table->increments('id_feria');
            $table->string('nombre_feria');
            $table->string('tipo_credenciales')->nullable();
            $table->date('inicio')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->boolean('acreditacion_empresa_habilitada')->default(false);
        });

        DB::table('eventos')->insert([
            'id_feria' => 1,
            'nombre_feria' => 'Evento de prueba',
            'tipo_credenciales' => 'Expositor',
            'fecha_fin' => today()->addDay()->toDateString(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        Config::set('database.default', $this->previousDatabaseDefault);

        parent::tearDown();
    }

    public function test_user_with_accreditation_edit_permission_can_enable_an_event_for_plataforma(): void
    {
        $user = $this->createUserWithPermissions([
            'credenciales.empresas.ver',
            'credenciales.acreditados.editar',
        ]);

        $this->actingAs($user)
            ->patchJson('/credenciales/eventos/1/acreditacion-empresa', ['habilitada' => true])
            ->assertOk()
            ->assertJsonPath('data.acreditacion_empresa_habilitada', true);

        $this->actingAs($user)
            ->getJson('/credenciales/eventos')
            ->assertOk()
            ->assertJsonPath('data.0.acreditacion_empresa_habilitada', true);
    }

    public function test_user_without_accreditation_edit_permission_cannot_change_the_event_flag(): void
    {
        $user = $this->createUserWithPermissions(['credenciales.empresas.ver']);

        $this->actingAs($user)
            ->patchJson('/credenciales/eventos/1/acreditacion-empresa', ['habilitada' => true])
            ->assertForbidden();

        $this->assertFalse((bool) DB::table('eventos')->where('id_feria', 1)->value('acreditacion_empresa_habilitada'));
    }

    private function createUserWithPermissions(array $permissionNames): Usuario
    {
        $userId = DB::table('usuarios')->insertGetId([
            'nick_usuario' => 'gestor',
            'nombre_usuario' => 'Gestor',
            'estado' => true,
        ]);

        foreach ($permissionNames as $name) {
            [$module, $action] = explode('.', $name, 2);
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => $name,
                'module' => $module,
                'action' => $action,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('user_permissions')->insert([
                'user_id' => $userId,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return Usuario::query()->findOrFail($userId);
    }
}
