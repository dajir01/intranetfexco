<?php

namespace Tests\Feature;

use App\Http\Controllers\CredencialController;
use App\Http\Controllers\CredencialPinController;
use App\Models\Contrato;
use App\Models\Usuario;
use App\Services\AnularCredencialesContratoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class ContratoCredencialesLifecycleTest extends TestCase
{
    private string $previousDatabaseDefault;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-03 12:00:00');
        $this->previousDatabaseDefault = config('database.default');
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('eventos', function (Blueprint $table): void {
            $table->increments('id_feria');
            $table->string('nombre_feria');
            $table->date('inicio')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin');
            $table->string('tipo_credenciales')->nullable();
        });
        Schema::create('empresas', function (Blueprint $table): void {
            $table->increments('id_empresa');
            $table->string('nombre_empresa');
        });
        Schema::create('contrato', function (Blueprint $table): void {
            $table->increments('id_contrato');
            $table->unsignedInteger('id_empresa');
            $table->unsignedInteger('id_feria');
            $table->unsignedInteger('codigo_contrato');
            $table->unsignedInteger('total_stands');
            $table->unsignedInteger('nro_credenciales');
            $table->unsignedInteger('tipo_credenciales')->nullable();
            $table->unsignedInteger('estado_reserva');
        });
        Schema::create('usuarios', function (Blueprint $table): void {
            $table->increments('id_usuario');
            $table->string('nick_usuario');
            $table->string('pass_usuario')->nullable();
            $table->boolean('estado')->default(true);
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
        });
        Schema::create('credenciales', function (Blueprint $table): void {
            $table->increments('id_credencial');
            $table->unsignedInteger('id_contrato')->nullable();
            $table->unsignedInteger('id_feria');
            $table->unsignedInteger('id_empresa');
            $table->unsignedInteger('tipo_credencial')->default(1);
            $table->unsignedInteger('estado_credencial');
            $table->timestamps();
        });
        Schema::create('credencial_qr_tokens', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_credencial');
            $table->unsignedInteger('version');
            $table->boolean('activo');
            $table->timestamp('revocado_at')->nullable();
            $table->string('motivo_revocacion')->nullable();
            $table->unsignedInteger('id_usuario_revocacion')->nullable();
            $table->timestamps();
        });
        Schema::create('credencial_estado_historial', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_credencial');
            $table->string('accion');
            $table->unsignedInteger('estado_anterior');
            $table->unsignedInteger('estado_nuevo');
            $table->text('motivo')->nullable();
            $table->unsignedInteger('version_qr_anterior')->nullable();
            $table->unsignedInteger('id_usuario')->nullable();
            $table->timestamps();
        });
        Schema::create('empresa_evento_credenciales', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_empresa')->default(7);
            $table->unsignedInteger('id_feria')->default(4);
            $table->string('tipo_credencial')->default('Expositor');
            $table->unsignedInteger('tipo_credenciales')->default(1);
            $table->unsignedInteger('cantidad_credenciales')->default(3);
            $table->unsignedInteger('id_contrato')->nullable();
            $table->boolean('estado')->default(true);
        });
        Schema::create('cred_adicionales', function (Blueprint $table): void {
            $table->increments('id_adic');
            $table->unsignedInteger('feria');
            $table->unsignedInteger('contrato')->nullable();
            $table->unsignedInteger('id_empresa')->nullable();
            $table->unsignedInteger('id_empresa_evento_credencial')->nullable();
            $table->unsignedInteger('cant_adic')->default(0);
            $table->string('tipo_movimiento')->default('adicion');
        });
        Schema::create('credencial_pines', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('id_feria');
            $table->unsignedInteger('id_empresa');
            $table->unsignedInteger('id_contrato')->nullable();
            $table->unsignedInteger('id_empresa_evento_credencial')->nullable();
            $table->string('tipo_credencial');
            $table->unsignedInteger('modalidad_credencial')->default(0);
            $table->string('pin_hash');
            $table->text('pin_encrypted');
            $table->string('estado');
            $table->unsignedInteger('intentos_fallidos')->default(0);
            $table->unsignedInteger('generado_por')->nullable();
            $table->timestamp('revocado_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        Config::set('database.default', $this->previousDatabaseDefault);
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_contract_adjustments_without_company_id_are_included_and_scoped_by_contract(): void
    {
        DB::table('cred_adicionales')->insert([
            ['feria' => 4, 'id_empresa' => null, 'contrato' => 10, 'cant_adic' => 1, 'tipo_movimiento' => 'adicion'],
            ['feria' => 4, 'id_empresa' => 7, 'contrato' => 10, 'cant_adic' => 1, 'tipo_movimiento' => 'adicion'],
            ['feria' => 4, 'id_empresa' => 8, 'contrato' => 10, 'cant_adic' => 9, 'tipo_movimiento' => 'adicion'],
            ['feria' => 4, 'id_empresa' => null, 'contrato' => 11, 'cant_adic' => 5, 'tipo_movimiento' => 'adicion'],
        ]);

        $method = (new ReflectionClass(CredencialController::class))->getMethod('credencialesAdicionalesNetas');
        $method->setAccessible(true);

        $this->assertSame(2, $method->invoke(new CredencialController, 4, 7, 10, null));

        $pinMethod = (new ReflectionClass(CredencialPinController::class))->getMethod('adicionalesNetos');
        $pinMethod->setAccessible(true);

        $this->assertSame(2, $pinMethod->invoke(new CredencialPinController, 4, 7, 10, null));
    }

    public function test_contract_cancellation_revokes_its_credentials_qr_tokens_and_unused_pins(): void
    {
        DB::table('credenciales')->insert([
            ['id_contrato' => 10, 'id_feria' => 4, 'id_empresa' => 7, 'estado_credencial' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id_contrato' => 10, 'id_feria' => 4, 'id_empresa' => 7, 'estado_credencial' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id_contrato' => 11, 'id_feria' => 4, 'id_empresa' => 7, 'estado_credencial' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id_contrato' => 10, 'id_feria' => 4, 'id_empresa' => 8, 'estado_credencial' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id_contrato' => 10, 'id_feria' => 4, 'id_empresa' => 7, 'estado_credencial' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['id_contrato' => 10, 'id_feria' => 4, 'id_empresa' => 7, 'estado_credencial' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('credencial_qr_tokens')->insert([
            ['id_credencial' => 1, 'version' => 2, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id_credencial' => 3, 'version' => 1, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('empresa_evento_credenciales')->insert(['id' => 30, 'id_contrato' => 10]);
        DB::table('credencial_pines')->insert([
            ['id_feria' => 4, 'id_empresa' => 7, 'id_contrato' => 10, 'id_empresa_evento_credencial' => null, 'tipo_credencial' => 'Expositor', 'pin_hash' => 'directo', 'pin_encrypted' => 'pin', 'estado' => 'disponible', 'created_at' => now(), 'updated_at' => now()],
            ['id_feria' => 4, 'id_empresa' => 7, 'id_contrato' => null, 'id_empresa_evento_credencial' => 30, 'tipo_credencial' => 'Expositor', 'pin_hash' => 'vinculado', 'pin_encrypted' => 'pin', 'estado' => 'enviado', 'created_at' => now(), 'updated_at' => now()],
            ['id_feria' => 4, 'id_empresa' => 7, 'id_contrato' => 11, 'id_empresa_evento_credencial' => null, 'tipo_credencial' => 'Expositor', 'pin_hash' => 'otro-contrato', 'pin_encrypted' => 'pin', 'estado' => 'disponible', 'created_at' => now(), 'updated_at' => now()],
            ['id_feria' => 4, 'id_empresa' => 7, 'id_contrato' => 10, 'id_empresa_evento_credencial' => null, 'tipo_credencial' => 'Expositor', 'pin_hash' => 'usado', 'pin_encrypted' => 'pin', 'estado' => 'usado', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $contrato = new Contrato;
        $contrato->id_contrato = 10;
        $contrato->id_feria = 4;
        $contrato->id_empresa = 7;
        $contrato->codigo_contrato = 'CT-10';
        $this->assertSame(10, $contrato->id_contrato);
        $this->assertSame(4, DB::table('credenciales')->where('id_contrato', 10)->where('id_feria', 4)->where('id_empresa', 7)->count());

        (new AnularCredencialesContratoService)->ejecutar($contrato);

        $this->assertSame([2, 2, 1, 1, 2, 3], DB::table('credenciales')->orderBy('id_credencial')->pluck('estado_credencial')->all());
        $this->assertFalse((bool) DB::table('credencial_qr_tokens')->where('id_credencial', 1)->value('activo'));
        $this->assertTrue((bool) DB::table('credencial_qr_tokens')->where('id_credencial', 3)->value('activo'));
        $this->assertSame(2, DB::table('credencial_estado_historial')->count());
        $this->assertSame(['revocado', 'revocado', 'disponible', 'usado'], DB::table('credencial_pines')->orderBy('id')->pluck('estado')->all());
    }

    public function test_public_auto_accreditation_lists_only_current_and_upcoming_events(): void
    {
        DB::table('eventos')->insert([
            ['id_feria' => 1, 'nombre_feria' => 'Pasado', 'inicio' => null, 'fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-11-02', 'tipo_credenciales' => 'Expositor'],
            ['id_feria' => 2, 'nombre_feria' => 'En curso', 'inicio' => null, 'fecha_inicio' => '2026-11-01', 'fecha_fin' => '2026-11-03', 'tipo_credenciales' => 'Expositor'],
            ['id_feria' => 3, 'nombre_feria' => 'Próximo', 'inicio' => null, 'fecha_inicio' => '2026-11-10', 'fecha_fin' => '2026-11-12', 'tipo_credenciales' => 'Expositor'],
            ['id_feria' => 4, 'nombre_feria' => 'Sin credenciales', 'inicio' => null, 'fecha_inicio' => '2026-11-10', 'fecha_fin' => '2026-11-12', 'tipo_credenciales' => ''],
        ]);

        $this->getJson('/api/autoacreditacion/eventos')
            ->assertOk()
            ->assertJsonPath('data.0.id_feria', 2)
            ->assertJsonPath('data.1.id_feria', 3)
            ->assertJsonCount(2, 'data');
    }

    public function test_public_pin_validation_rejects_an_expired_event(): void
    {
        DB::table('eventos')->insert([
            'id_feria' => 1,
            'nombre_feria' => 'Evento pasado',
            'inicio' => null,
            'fecha_inicio' => '2026-10-01',
            'fecha_fin' => '2026-11-02',
            'tipo_credenciales' => 'Expositor',
        ]);
        DB::table('empresas')->insert(['id_empresa' => 7, 'nombre_empresa' => 'Empresa']);
        DB::table('credencial_pines')->insert([
            'id' => 1,
            'id_feria' => 1,
            'id_empresa' => 7,
            'id_contrato' => 10,
            'id_empresa_evento_credencial' => null,
            'tipo_credencial' => 'Expositor',
            'modalidad_credencial' => 0,
            'pin_hash' => hash('sha256', 'ABC123'),
            'pin_encrypted' => 'pin',
            'estado' => 'disponible',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/autoacreditacion/validar-pin', ['id_feria' => 1, 'pin' => 'ABC123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pin']);
    }

    public function test_pin_generation_rejects_an_annulled_contract(): void
    {
        $this->createPinGeneratorUser();
        DB::table('eventos')->insert([
            'id_feria' => 4,
            'nombre_feria' => 'Feria',
            'inicio' => null,
            'fecha_inicio' => '2026-11-01',
            'fecha_fin' => '2026-11-10',
            'tipo_credenciales' => 'Expositor',
        ]);
        DB::table('empresas')->insert(['id_empresa' => 7, 'nombre_empresa' => 'Empresa']);
        DB::table('contrato')->insert([
            'id_contrato' => 10,
            'id_empresa' => 7,
            'id_feria' => 4,
            'codigo_contrato' => 100,
            'total_stands' => 0,
            'nro_credenciales' => 3,
            'tipo_credenciales' => 1,
            'estado_reserva' => 3,
        ]);

        $this->actingAs(Usuario::query()->findOrFail(1))
            ->postJson('/credenciales/eventos/4/empresas/7/pines', [
                'id_contrato' => 10,
                'tipo_credencial' => 'Expositor',
                'cantidad' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_contrato']);

        $this->assertSame(0, DB::table('credencial_pines')->count());
    }

    public function test_pin_generation_rejects_an_annulled_contract_selected_through_its_linkage(): void
    {
        $this->createPinGeneratorUser();
        DB::table('eventos')->insert([
            'id_feria' => 4,
            'nombre_feria' => 'Feria',
            'inicio' => null,
            'fecha_inicio' => '2026-11-01',
            'fecha_fin' => '2026-11-10',
            'tipo_credenciales' => 'Expositor',
        ]);
        DB::table('empresas')->insert(['id_empresa' => 7, 'nombre_empresa' => 'Empresa']);
        DB::table('contrato')->insert([
            'id_contrato' => 10,
            'id_empresa' => 7,
            'id_feria' => 4,
            'codigo_contrato' => 100,
            'total_stands' => 0,
            'nro_credenciales' => 3,
            'tipo_credenciales' => 1,
            'estado_reserva' => 3,
        ]);
        DB::table('empresa_evento_credenciales')->insert([
            'id' => 30,
            'id_empresa' => 7,
            'id_feria' => 4,
            'tipo_credencial' => 'Expositor',
            'tipo_credenciales' => 1,
            'cantidad_credenciales' => 3,
            'id_contrato' => 10,
            'estado' => true,
        ]);

        $this->actingAs(Usuario::query()->findOrFail(1))
            ->postJson('/credenciales/eventos/4/empresas/7/pines', [
                'id_vinculacion' => 30,
                'tipo_credencial' => 'Expositor',
                'cantidad' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_contrato']);

        $this->assertSame(0, DB::table('credencial_pines')->count());
    }

    public function test_pin_generation_remains_available_for_a_formalized_contract(): void
    {
        $this->createPinGeneratorUser();
        DB::table('eventos')->insert([
            'id_feria' => 4,
            'nombre_feria' => 'Feria',
            'inicio' => null,
            'fecha_inicio' => '2026-11-01',
            'fecha_fin' => '2026-11-10',
            'tipo_credenciales' => 'Expositor',
        ]);
        DB::table('empresas')->insert(['id_empresa' => 7, 'nombre_empresa' => 'Empresa']);
        DB::table('contrato')->insert([
            'id_contrato' => 10,
            'id_empresa' => 7,
            'id_feria' => 4,
            'codigo_contrato' => 100,
            'total_stands' => 2,
            'nro_credenciales' => 3,
            'tipo_credenciales' => 1,
            'estado_reserva' => 3,
        ]);

        $this->actingAs(Usuario::query()->findOrFail(1))
            ->postJson('/credenciales/eventos/4/empresas/7/pines', [
                'id_contrato' => 10,
                'tipo_credencial' => 'Expositor',
                'cantidad' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.cantidad', 1);

        $this->assertSame(1, DB::table('credencial_pines')->count());
    }

    private function createPinGeneratorUser(): void
    {
        DB::table('usuarios')->insert([
            'id_usuario' => 1,
            'nick_usuario' => 'credenciales-test',
            'pass_usuario' => 'test',
            'estado' => true,
        ]);
        foreach (['credenciales.empresas.ver', 'credenciales.pines.generar'] as $name) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => $name,
                'module' => 'credenciales',
                'action' => 'generar',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('user_permissions')->insert([
                'user_id' => 1,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
