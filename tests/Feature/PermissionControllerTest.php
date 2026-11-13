<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PermissionControllerTest extends TestCase
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
            $table->string('nombre_usuario');
            $table->string('nick_usuario')->unique();
            $table->string('email')->nullable();
            $table->string('area')->nullable();
            $table->integer('nivel_usuario')->default(1);
            $table->boolean('jefatura')->default(false);
            $table->boolean('estado')->default(true);
            $table->string('pass_usuario')->nullable();
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
            $table->bigIncrements('id_feria');
            $table->string('nombre_feria');
            $table->string('puertas_acceso')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->integer('inicio')->nullable();
        });
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        Config::set('database.default', $this->previousDatabaseDefault);

        parent::tearDown();
    }

    public function test_catalog_and_sync_only_accept_configured_permissions(): void
    {
        $managerId = DB::table('usuarios')->insertGetId([
            'nombre_usuario' => 'Gestor',
            'nick_usuario' => 'gestor',
        ]);
        $targetId = DB::table('usuarios')->insertGetId([
            'nombre_usuario' => 'Usuario destino',
            'nick_usuario' => 'destino',
        ]);

        $permissionIds = [];
        foreach ([
            'usuarios.ver',
            'usuarios.crear',
            'usuarios.editar',
            'usuarios.gestionar_permisos',
            'usuarios.eliminar',
            'noticias.ver',
            'contratos.exportar',
            'pagos.ver_comprobantes',
            'pagos.ver_recibos',
            'controlparqueos.exportar',
            'controlaccesos.operadores.ver',
            'controlaccesos.operadores.gestionar',
            'informes.gestionar',
            'informes.administrar_plantilla',
        ] as $name) {
            [$module, $action] = explode('.', $name, 2);
            $permissionIds[$name] = DB::table('permissions')->insertGetId([
                'name' => $name,
                'module' => $module,
                'action' => $action,
            ]);
        }

        DB::table('user_permissions')->insert([
            [
                'user_id' => $managerId,
                'permission_id' => $permissionIds['usuarios.gestionar_permisos'],
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $managerId,
                'permission_id' => $permissionIds['noticias.ver'],
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $targetId,
                'permission_id' => $permissionIds['contratos.exportar'],
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs(Usuario::query()->findOrFail($managerId));

        $this->getJson('/dashboard/resumen-ferias')->assertForbidden();
        $this->getJson('/dashboard/resumen/1')->assertForbidden();

        $catalogResponse = $this->getJson('/permissions')->assertOk();
        $catalog = $catalogResponse->json('data.permissions');
        $this->assertContains(
            'solicitudes.editar_certificacion_presupuestaria',
            $catalogResponse->json('data.missing_permissions'),
        );
        $this->assertNotContains('usuarios.eliminar', collect($catalog)->pluck('name')->all());
        $this->assertContains('usuarios.gestionar_permisos', collect($catalog)->pluck('name')->all());
        $this->assertContains('pagos.ver_comprobantes', collect($catalog)->pluck('name')->all());
        $this->assertContains('pagos.ver_recibos', collect($catalog)->pluck('name')->all());
        $this->assertContains('controlparqueos.exportar', collect($catalog)->pluck('name')->all());
        $this->assertContains('controlaccesos.operadores.ver', collect($catalog)->pluck('name')->all());
        $this->assertContains('controlaccesos.operadores.gestionar', collect($catalog)->pluck('name')->all());
        $this->assertContains('informes.gestionar', collect($catalog)->pluck('name')->all());
        $this->assertContains('informes.administrar_plantilla', collect($catalog)->pluck('name')->all());
        $this->assertArrayHasKey('informes', $catalogResponse->json('data.grouped'));
        $this->assertCount(2, $catalogResponse->json('data.grouped.informes'));
        $this->getJson("/usuarios/{$targetId}/permissions")->assertOk();

        $this->putJson("/usuarios/{$targetId}/permissions", [
            'permissions' => ['usuarios.eliminar'],
        ])->assertUnprocessable();

        $this->putJson("/usuarios/{$targetId}/permissions", [
            'permissions' => [
                'noticias.ver',
                'pagos.ver_comprobantes',
                'pagos.ver_recibos',
                'controlparqueos.exportar',
                'controlaccesos.operadores.ver',
            ],
        ])->assertOk()
            ->assertJsonFragment(['noticias.ver'])
            ->assertJsonFragment(['pagos.ver_comprobantes'])
            ->assertJsonFragment(['pagos.ver_recibos'])
            ->assertJsonFragment(['controlparqueos.exportar'])
            ->assertJsonFragment(['controlaccesos.operadores.ver']);

        $this->putJson("/usuarios/{$managerId}/permissions", [
            'permissions' => [
                'usuarios.gestionar_permisos',
                'noticias.ver',
                'usuarios.crear',
                'pagos.ver_comprobantes',
            ],
        ])->assertOk()
            ->assertJsonFragment(['usuarios.gestionar_permisos'])
            ->assertJsonFragment(['noticias.ver'])
            ->assertJsonFragment(['usuarios.crear'])
            ->assertJsonFragment(['pagos.ver_comprobantes']);

        $unprivilegedTargetId = DB::table('usuarios')->insertGetId([
            'nombre_usuario' => 'Otro usuario',
            'nick_usuario' => 'otro',
        ]);

        $this->actingAs(Usuario::query()->findOrFail($unprivilegedTargetId));
        $this->putJson("/usuarios/{$unprivilegedTargetId}/permissions", [
            'permissions' => ['contratos.exportar'],
        ])->assertForbidden();
    }

    public function test_payment_read_permissions_are_independent(): void
    {
        $userId = DB::table('usuarios')->insertGetId([
            'nombre_usuario' => 'Consulta pagos',
            'nick_usuario' => 'consulta-pagos',
        ]);

        $permissionIds = [];
        foreach ([
            'pagos.ver',
            'pagos.ver_comprobantes',
            'pagos.ver_recibos',
        ] as $name) {
            [$module, $action] = explode('.', $name, 2);
            $permissionIds[$name] = DB::table('permissions')->insertGetId([
                'name' => $name,
                'module' => $module,
                'action' => $action,
            ]);
        }

        DB::table('user_permissions')->insert([
            'user_id' => $userId,
            'permission_id' => $permissionIds['pagos.ver'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = Usuario::query()->findOrFail($userId);
        $this->assertTrue($user->hasPermission('pagos.view'));
        $this->assertFalse($user->hasPermission('pagos.attachments.view'));
        $this->assertFalse($user->hasPermission('pagos.receipts.view'));

        $this->actingAs($user)
            ->getJson('/pagos/1/archivo')
            ->assertForbidden();

        $this->getJson('/pagos/1/recibo')->assertForbidden();

        DB::table('user_permissions')->insert([
            'user_id' => $userId,
            'permission_id' => $permissionIds['pagos.ver_comprobantes'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user->unsetRelation('permissions');
        $this->assertTrue($user->hasPermission('pagos.attachments.view'));
        $this->assertFalse($user->hasPermission('pagos.receipts.view'));
    }

    public function test_operator_view_permission_allows_listing_but_not_management(): void
    {
        $userId = DB::table('usuarios')->insertGetId([
            'nombre_usuario' => 'Consulta operadores',
            'nick_usuario' => 'consulta-operadores',
        ]);
        $permissionId = DB::table('permissions')->insertGetId([
            'name' => 'controlaccesos.operadores.ver',
            'module' => 'controlaccesos',
            'action' => 'operadores.ver',
        ]);
        DB::table('user_permissions')->insert([
            'user_id' => $userId,
            'permission_id' => $permissionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(Usuario::query()->findOrFail($userId));

        $this->getJson('/control-accesos/operadores/eventos')->assertOk();
        $this->postJson('/control-accesos/operadores', [])->assertForbidden();
    }

    public function test_company_credential_permission_does_not_grant_access_to_accredited_person_data(): void
    {
        $userId = DB::table('usuarios')->insertGetId([
            'nombre_usuario' => 'Consulta empresas',
            'nick_usuario' => 'consulta-empresas',
        ]);
        $permissionId = DB::table('permissions')->insertGetId([
            'name' => 'credenciales.empresas.ver',
            'module' => 'credenciales.empresas',
            'action' => 'ver',
        ]);
        DB::table('user_permissions')->insert([
            'user_id' => $userId,
            'permission_id' => $permissionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(Usuario::query()->findOrFail($userId))
            ->getJson('/credenciales/eventos/1/acreditados')
            ->assertForbidden();
    }

    public function test_platform_visibility_permission_is_configured_visible_assignable_and_backfilled(): void
    {
        $managerId = DB::table('usuarios')->insertGetId([
            'nombre_usuario' => 'Gestor Plataforma',
            'nick_usuario' => 'gestor-plataforma',
        ]);
        $existingUserId = DB::table('usuarios')->insertGetId([
            'nombre_usuario' => 'Usuario con empresas',
            'nick_usuario' => 'usuario-empresas',
        ]);

        $managePermissionsId = DB::table('permissions')->insertGetId([
            'name' => 'usuarios.gestionar_permisos',
            'module' => 'usuarios',
            'action' => 'gestionar_permisos',
        ]);
        $createCompanyPermissionId = DB::table('permissions')->insertGetId([
            'name' => 'credenciales.empresas.crear',
            'module' => 'credenciales.empresas',
            'action' => 'crear',
        ]);

        DB::table('user_permissions')->insert([
            [
                'user_id' => $managerId,
                'permission_id' => $managePermissionsId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $existingUserId,
                'permission_id' => $createCompanyPermissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $permissionConfig = collect(config('acl.permissions'))->first(
            fn (array $permission): bool => $permission['module'] === 'credenciales.empresas'
                && $permission['action'] === 'plataforma',
        );
        $this->assertNotNull($permissionConfig);
        $this->assertSame(
            ['admin', 'comercial_access'],
            config('permissions.abilities')['credenciales.empresas.plataforma'],
        );

        $migration = require database_path('migrations/2026_11_11_000002_add_credencial_empresa_platform_visibility_permission.php');
        $migration->up();
        $platformPermissionId = DB::table('permissions')
            ->where('name', 'credenciales.empresas.plataforma')
            ->value('id');

        $this->assertDatabaseHas('user_permissions', [
            'user_id' => $existingUserId,
            'permission_id' => $platformPermissionId,
        ]);

        $this->actingAs(Usuario::query()->findOrFail($managerId));
        $catalog = $this->getJson('/permissions')->assertOk()->json('data.permissions');
        $this->assertContains('credenciales.empresas.plataforma', collect($catalog)->pluck('name')->all());

        $newUserId = DB::table('usuarios')->insertGetId([
            'nombre_usuario' => 'Usuario Plataforma',
            'nick_usuario' => 'usuario-plataforma',
        ]);
        $this->putJson("/usuarios/{$newUserId}/permissions", [
            'permissions' => ['credenciales.empresas.plataforma'],
        ])->assertOk();

        $this->assertTrue(
            Usuario::query()->findOrFail($newUserId)->hasPermission('credenciales.empresas.plataforma')
        );
    }

    public function test_contract_print_permission_grants_read_access_without_edit_access(): void
    {
        $userId = DB::table('usuarios')->insertGetId([
            'nombre_usuario' => 'Impresión contratos',
            'nick_usuario' => 'impresion-contratos',
        ]);
        $permissionId = DB::table('permissions')->insertGetId([
            'name' => 'contratos.imprimir',
            'module' => 'contratos',
            'action' => 'imprimir',
        ]);

        DB::table('user_permissions')->insert([
            'user_id' => $userId,
            'permission_id' => $permissionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = Usuario::query()->findOrFail($userId);

        $this->assertTrue($user->hasPermission('contratos.view'));
        $this->assertTrue($user->hasPermission('contratos.print'));
        $this->assertFalse($user->hasPermission('contratos.edit'));
    }

    public function test_certification_permissions_are_visible_and_assignable(): void
    {
        $managerId = DB::table('usuarios')->insertGetId([
            'nombre_usuario' => 'Gestor permisos',
            'nick_usuario' => 'gestor-certificaciones',
        ]);
        $targetId = DB::table('usuarios')->insertGetId([
            'nombre_usuario' => 'Usuario certificaciones',
            'nick_usuario' => 'usuario-certificaciones',
        ]);

        $permissionIds = [];
        foreach ([
            'usuarios.gestionar_permisos',
            'solicitudes.ver_certificacion_presupuestaria',
            'solicitudes.crear_certificacion_presupuestaria',
            'solicitudes.editar_certificacion_presupuestaria',
            'solicitudes.aprobar_certificacion_presupuestaria',
        ] as $name) {
            [$module, $action] = explode('.', $name, 2);
            $permissionIds[$name] = DB::table('permissions')->insertGetId([
                'name' => $name,
                'module' => $module,
                'action' => $action,
            ]);
        }

        DB::table('user_permissions')->insert([
            'user_id' => $managerId,
            'permission_id' => $permissionIds['usuarios.gestionar_permisos'],
        ]);

        $this->actingAs(Usuario::query()->findOrFail($managerId));

        $catalog = $this->getJson('/permissions')->assertOk()->json('data.permissions');
        $names = collect($catalog)->pluck('name')->all();
        $certificationPermissions = [
            'solicitudes.ver_certificacion_presupuestaria',
            'solicitudes.crear_certificacion_presupuestaria',
            'solicitudes.editar_certificacion_presupuestaria',
            'solicitudes.aprobar_certificacion_presupuestaria',
        ];
        foreach ($certificationPermissions as $name) {
            $this->assertContains($name, $names);
        }

        $this->putJson("/usuarios/{$targetId}/permissions", [
            'permissions' => $certificationPermissions,
        ])->assertOk();

        foreach ($certificationPermissions as $name) {
            $this->assertDatabaseHas('user_permissions', [
                'user_id' => $targetId,
                'permission_id' => $permissionIds[$name],
            ]);
        }
    }

    public function test_invalid_permission_assignment_returns_clear_error_and_is_logged(): void
    {
        $managerId = DB::table('usuarios')->insertGetId([
            'nombre_usuario' => 'Gestor permisos',
            'nick_usuario' => 'gestor-validacion',
        ]);
        $targetId = DB::table('usuarios')->insertGetId([
            'nombre_usuario' => 'Usuario destino',
            'nick_usuario' => 'destino-validacion',
        ]);
        $permissionId = DB::table('permissions')->insertGetId([
            'name' => 'usuarios.gestionar_permisos',
            'module' => 'usuarios',
            'action' => 'gestionar_permisos',
        ]);
        DB::table('user_permissions')->insert([
            'user_id' => $managerId,
            'permission_id' => $permissionId,
        ]);

        Log::spy();

        $response = $this->actingAs(Usuario::query()->findOrFail($managerId))
            ->putJson("/usuarios/{$targetId}/permissions", [
                'permissions' => ['permiso.inexistente'],
            ]);

        $response->assertUnprocessable()
            ->assertJsonFragment([
                'permissions.0' => ['Uno o más permisos no pertenecen al catálogo autorizado.'],
            ]);

        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context): bool => $message === 'Datos inválidos al asignar permisos a un usuario.'
                && $context['usuario_destino_id'] === $targetId
                && $context['errores_validacion']['permissions.0'][0] === 'Uno o más permisos no pertenecen al catálogo autorizado.',
        );
    }
}
